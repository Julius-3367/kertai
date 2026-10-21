<?php

namespace App\Domain\Members;

use App\Domain\Dues\Models\Due;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Models\JournalLine;
use App\Domain\Members\Models\Member;
use Carbon\CarbonImmutable;

/**
 * A member's money position, read from the ledger and their dues.
 */
final class MemberFinance
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly ChartOfAccounts $accounts,
    ) {}

    public function savings(Member $member): int
    {
        return $this->ledger->balance($this->accounts->get($member->group_id, ChartOfAccounts::SAVINGS), $member->id);
    }

    public function prepayment(Member $member): int
    {
        return $this->ledger->balance($this->accounts->get($member->group_id, ChartOfAccounts::PREPAYMENTS), $member->id);
    }

    public function outstanding(Member $member): int
    {
        return (int) Due::query()->where('member_id', $member->id)->open()->get()->sum(fn (Due $d) => $d->outstanding());
    }

    public function arrears(Member $member, ?CarbonImmutable $on = null): int
    {
        return (int) Due::query()->where('member_id', $member->id)->overdue($on)->get()->sum(fn (Due $d) => $d->outstanding());
    }

    public function finesOutstanding(Member $member): int
    {
        return (int) Due::query()->where('member_id', $member->id)->where('type', 'fine')->open()->get()->sum(fn (Due $d) => $d->outstanding());
    }

    /**
     * What the member would get back on exit: savings and prepayments less
     * anything they still owe.
     */
    public function exitRefund(Member $member): int
    {
        return max(0, $this->savings($member) + $this->prepayment($member) - $this->outstanding($member));
    }

    /**
     * @return array{savings: int, prepayment: int, outstanding: int, arrears: int, fines: int}
     */
    public function summary(Member $member): array
    {
        return [
            'savings' => $this->savings($member),
            'prepayment' => $this->prepayment($member),
            'outstanding' => $this->outstanding($member),
            'arrears' => $this->arrears($member),
            'fines' => $this->finesOutstanding($member),
        ];
    }

    /**
     * Ledger lines tagged with this member, oldest first, with a running
     * balance of their savings.
     *
     * @return list<array<string, mixed>>
     */
    public function statement(Member $member, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $lines = JournalLine::query()
            ->with(['entry' => fn ($q) => $q->withoutGlobalScopes(), 'account'])
            ->where('member_id', $member->id)
            ->whereHas('account', fn ($q) => $q->where('kind', 'fund'))
            ->whereHas('entry', function ($q) use ($from, $to) {
                $q->withoutGlobalScopes()
                    ->when($from, fn ($e) => $e->whereDate('entry_date', '>=', $from->toDateString()))
                    ->when($to, fn ($e) => $e->whereDate('entry_date', '<=', $to->toDateString()));
            })
            ->orderBy('journal_entry_id')
            ->orderBy('id')
            ->get();

        $savings = $from
            ? $this->ledger->balance($this->accounts->get($member->group_id, ChartOfAccounts::SAVINGS), $member->id, $from->subDay())
            : 0;

        return $lines->map(function (JournalLine $line) use (&$savings) {
            $amount = $line->credit - $line->debit;

            if ($line->account->purpose === 'savings') {
                $savings += $amount;
            }

            return [
                'id' => $line->id,
                'date' => $line->entry->entry_date->toDateString(),
                'entry_number' => $line->entry->number,
                'description' => $line->entry->description,
                'memo' => $line->memo,
                'fund' => $line->account->name,
                'purpose' => $line->account->purpose,
                'in' => $line->credit,
                'out' => $line->debit,
                'savings_balance' => $savings,
            ];
        })->all();
    }
}
