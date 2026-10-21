<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Audit\Audit;
use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\JournalEntry;
use App\Domain\Ledger\Models\JournalLine;
use App\Domain\Members\Models\Member;
use App\Domain\Payments\Actions\ReversePayment;
use App\Domain\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LedgerController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        $accounts = Account::query()->orderBy('code')->get();

        $entries = JournalEntry::query()
            ->with(['lines.account:id,code,name', 'lines.member:id,name', 'creator:id,name'])
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereLike('description', "%{$term}%")
                ->orWhereLike('reference', "%{$term}%")))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (JournalEntry $e) => $this->presentEntry($e));

        $balances = $accounts->map(fn (Account $a) => [
            'id' => $a->id,
            'code' => $a->code,
            'name' => $a->name,
            'kind' => $a->kind,
            'purpose' => $a->purpose,
            'balance' => $ledger->balance($a),
        ]);

        return Inertia::render('ledger/index', [
            'accounts' => $balances,
            'totals' => [
                'assets' => $balances->where('kind', 'asset')->sum('balance'),
                'funds' => $balances->where('kind', 'fund')->sum('balance'),
            ],
            'entries' => $entries,
            'filters' => $request->only('q'),
            'members' => $this->memberOptions(false),
        ]);
    }

    public function account(Request $request, Account $account, Ledger $ledger): Response
    {
        $lines = JournalLine::query()
            ->with(['entry' => fn ($q) => $q->withoutGlobalScopes(), 'member:id,name'])
            ->where('account_id', $account->id)
            ->when($request->integer('member_id'), fn ($q, $m) => $q->where('member_id', $m))
            ->orderBy('journal_entry_id')
            ->orderBy('id')
            ->get();

        $running = 0;
        $rows = $lines->map(function (JournalLine $l) use ($account, &$running) {
            $running += $account->isAsset() ? $l->debit - $l->credit : $l->credit - $l->debit;

            return [
                'id' => $l->id,
                'date' => $l->entry->entry_date->toDateString(),
                'entry_id' => $l->entry->id,
                'number' => $l->entry->number,
                'description' => $l->entry->description,
                'memo' => $l->memo,
                'member' => $l->member?->name,
                'debit' => $l->debit,
                'credit' => $l->credit,
                'balance' => $running,
            ];
        })->reverse()->values();

        return Inertia::render('ledger/account', [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'kind' => $account->kind,
                'per_member' => $account->isPerMember(),
                'balance' => $ledger->balance($account),
            ],
            'lines' => $rows,
            'memberBalances' => $account->isPerMember()
                ? collect($ledger->memberBalances($account))->map(fn ($balance, $memberId) => [
                    'member_id' => $memberId,
                    'name' => Member::find($memberId)?->name,
                    'balance' => $balance,
                ])->values()
                : [],
        ]);
    }

    /**
     * Moving money between where it is held, e.g. banking meeting cash.
     */
    public function transfer(Request $request, Ledger $ledger): RedirectResponse
    {
        $groupId = $this->group()->id;
        $data = $request->validate([
            'from_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('group_id', $groupId)->where('kind', 'asset')],
            'to_account_id' => ['required', 'integer', 'different:from_account_id', Rule::exists('accounts', 'id')->where('group_id', $groupId)->where('kind', 'asset')],
            'amount' => Money::rule(),
            'description' => ['required', 'string', 'max:200'],
            'reference' => ['nullable', 'string', 'max:100'],
            'date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $from = Account::findOrFail((int) $data['from_account_id']);
        $to = Account::findOrFail((int) $data['to_account_id']);
        $amount = $this->cents($data['amount']);

        if ($ledger->balance($from) < $amount) {
            throw new RuleViolation("{$from->name} has only ".Money::format($ledger->balance($from)).'.');
        }

        $entry = $ledger->post($data['description'], [
            ['account' => $to, 'debit' => $amount, 'memo' => 'Transfer in'],
            ['account' => $from, 'credit' => $amount, 'memo' => 'Transfer out'],
        ], date: CarbonImmutable::parse($data['date']), reference: $data['reference'] ?? null);

        Audit::log('ledger.transfer', "Transfer #{$entry->number} of ".Money::format($amount)." from {$from->name} to {$to->name}", $entry);
        $this->toast('Transfer recorded.');

        return back();
    }

    /**
     * Opening balances when the group moves from the paper books.
     */
    public function opening(Request $request, Ledger $ledger): RedirectResponse
    {
        $groupId = $this->group()->id;
        $data = $request->validate([
            'asset_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('group_id', $groupId)->where('kind', 'asset')],
            'fund_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('group_id', $groupId)->where('kind', 'fund')],
            'member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->where('group_id', $groupId)],
            'amount' => Money::rule(),
            'date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $fund = Account::findOrFail((int) $data['fund_account_id']);

        if ($fund->isPerMember() && empty($data['member_id'])) {
            throw new RuleViolation("Opening balances for {$fund->name} are entered per member.");
        }

        $amount = $this->cents($data['amount']);
        $member = isset($data['member_id']) ? Member::find((int) $data['member_id']) : null;

        $entry = $ledger->post('Opening balance'.($member ? " for {$member->name}" : '')." ({$fund->name})", [
            ['account' => Account::findOrFail((int) $data['asset_account_id']), 'debit' => $amount, 'member_id' => $member?->id, 'memo' => 'Opening balance'],
            ['account' => $fund, 'credit' => $amount, 'member_id' => $member?->id, 'memo' => 'Opening balance'],
        ], date: CarbonImmutable::parse($data['date']), reference: $data['reference'] ?? 'OPENING');

        Audit::log('ledger.opening', "Opening balance #{$entry->number}: ".Money::format($amount)." to {$fund->name}", $entry);
        $this->toast('Opening balance posted.');

        return back();
    }

    public function reverse(Request $request, JournalEntry $entry, Ledger $ledger): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);

        if ($entry->source_type !== null) {
            throw new RuleViolation('This entry belongs to a payment or payout. Correct it from there so the records stay in step.');
        }

        $reversal = $ledger->reverse($entry, $data['reason']);
        Audit::log('ledger.reversed', "Entry #{$entry->number} reversed by #{$reversal->number}: {$data['reason']}", $entry);
        $this->toast("Entry #{$entry->number} reversed.");

        return back();
    }

    /**
     * Reverse a payment that was allocated by mistake (wrong member, typo).
     */
    public function reversePayment(Request $request, Payment $payment, ReversePayment $reverse): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);

        $reverse->handle($payment, $data['reason']);
        $this->toast('Payment reversed and its allocations undone.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentEntry(JournalEntry $e): array
    {
        return [
            'id' => $e->id,
            'number' => $e->number,
            'date' => $e->entry_date->toDateString(),
            'description' => $e->description,
            'reference' => $e->reference,
            'source' => $e->source_type,
            'reversal_of_id' => $e->reversal_of_id,
            'by' => $e->creator?->name,
            'total' => (int) $e->lines->sum('debit'),
            'lines' => $e->lines->map(fn (JournalLine $l) => [
                'account' => "{$l->account->code} {$l->account->name}",
                'member' => $l->member?->name,
                'debit' => $l->debit,
                'credit' => $l->credit,
                'memo' => $l->memo,
            ]),
        ];
    }
}
