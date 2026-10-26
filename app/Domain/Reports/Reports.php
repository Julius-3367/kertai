<?php

namespace App\Domain\Reports;

use App\Domain\Dues\Models\Due;
use App\Domain\Fundraisers\Models\Fundraiser;
use App\Domain\Groups\Models\Group;
use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\JournalLine;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Members\MemberFinance;
use App\Domain\Members\Models\Member;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payouts\Models\Payout;
use App\Domain\Projects\Models\Project;
use App\Domain\Welfare\Models\WelfareCase;
use Carbon\CarbonImmutable;

final class Reports
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly MemberFinance $finance,
    ) {}

    /**
     * Opening, money in, money out and closing for every account in a period.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fundMovements(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Account::query()->orderBy('code')->get()->map(function (Account $account) use ($from, $to) {
            $period = JournalLine::query()
                ->where('account_id', $account->id)
                ->whereHas('entry', fn ($q) => $q->whereDate('entry_date', '>=', $from->toDateString())->whereDate('entry_date', '<=', $to->toDateString()))
                ->toBase()
                ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
                ->first();

            $debit = (int) $period->d;
            $credit = (int) $period->c;

            return [
                'code' => $account->code,
                'name' => $account->name,
                'kind' => $account->kind,
                'opening' => $this->ledger->balance($account, until: $from->subDay()),
                'in' => $account->isAsset() ? $debit : $credit,
                'out' => $account->isAsset() ? $credit : $debit,
                'closing' => $this->ledger->balance($account, until: $to),
            ];
        })->all();
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, debits: int, credits: int}
     */
    public function trialBalance(CarbonImmutable $on): array
    {
        $rows = Account::query()->orderBy('code')->get()->map(function (Account $account) use ($on) {
            $balance = $this->ledger->balance($account, until: $on);

            return [
                'code' => $account->code,
                'name' => $account->name,
                'debit' => $account->isAsset() ? max(0, $balance) : max(0, -$balance),
                'credit' => $account->isAsset() ? max(0, -$balance) : max(0, $balance),
            ];
        })->all();

        return [
            'rows' => $rows,
            'debits' => array_sum(array_column($rows, 'debit')),
            'credits' => array_sum(array_column($rows, 'credit')),
        ];
    }

    /**
     * Contribution grid: one row per member, one column per month.
     *
     * @return array{months: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    public function contributionGrid(int $year): array
    {
        $months = array_map(fn ($m) => sprintf('%d-%02d', $year, $m), range(1, 12));
        $dues = Due::query()->where('type', 'contribution')->whereIn('period', $months)->get()->groupBy('member_id');

        $rows = Member::query()->orderBy('member_number')->get()->map(fn (Member $m) => [
            'member_id' => $m->id,
            'name' => $m->name,
            'member_number' => $m->member_number,
            'status' => $m->status,
            'cells' => collect($months)->map(function ($period) use ($dues, $m) {
                $due = $dues->get($m->id)?->firstWhere('period', $period);

                return $due ? ['amount' => $due->amount, 'paid' => $due->paid, 'status' => $due->status] : null;
            })->all(),
            'total_paid' => (int) ($dues->get($m->id)?->sum('paid') ?? 0),
        ])->all();

        return ['months' => $months, 'rows' => $rows];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function arrears(): array
    {
        return Member::query()->active()->orderBy('name')->get()
            ->map(fn (Member $m) => [
                'member_id' => $m->id,
                'name' => $m->name,
                'member_number' => $m->member_number,
                'phone' => $m->phone,
                'arrears' => $this->finance->arrears($m),
                'fines' => $this->finance->finesOutstanding($m),
                'oldest' => Due::query()->where('member_id', $m->id)->overdue()->orderBy('due_on')->value('due_on'),
            ])
            ->filter(fn ($r) => $r['arrears'] > 0)
            ->sortByDesc('arrears')
            ->values()
            ->all();
    }

    /**
     * The figures behind the overview charts: twelve months of money in and
     * out, this year's contributions month by month, and what each fund holds.
     *
     * @return array{
     *     cashflow: array<int, array{month: string, in: int, out: int}>,
     *     contributions: array<int, array{month: string, due: int, paid: int}>,
     *     funds: array<int, array{name: string, balance: int}>,
     *     totals: array{collected: int, paid_out: int, due_so_far: int, paid_so_far: int},
     * }
     */
    public function overview(CarbonImmutable $today): array
    {
        $months = collect(range(11, 0))->map(fn (int $i) => $today->startOfMonth()->subMonthsNoOverflow($i)->format('Y-m'));
        $from = CarbonImmutable::createFromFormat('Y-m-d', $months->first().'-01')->startOfDay();
        $yearStart = $today->startOfYear();

        $payments = Payment::query()->succeeded()->where('received_at', '>=', $from)->get(['amount', 'received_at']);
        $payouts = Payout::query()->where('status', 'paid')->where('paid_at', '>=', $from)->get(['amount', 'paid_at']);
        $in = $payments->groupBy(fn (Payment $p) => $p->received_at->format('Y-m'))->map(fn ($g) => (int) $g->sum('amount'));
        $out = $payouts->groupBy(fn (Payout $p) => $p->paid_at->format('Y-m'))->map(fn ($g) => (int) $g->sum('amount'));

        $dues = Due::query()->where('type', 'contribution')->where('status', '!=', 'waived')
            ->where('period', 'like', $today->year.'-%')->get(['period', 'amount', 'paid', 'due_on']);
        $falling = $dues->filter(fn (Due $d) => $d->due_on->lte($today));

        return [
            'cashflow' => $months->map(fn (string $m) => ['month' => $m, 'in' => $in[$m] ?? 0, 'out' => $out[$m] ?? 0])->values()->all(),
            'contributions' => collect(range(1, 12))->map(function (int $m) use ($dues, $today) {
                $month = $dues->where('period', sprintf('%d-%02d', $today->year, $m));

                return ['month' => sprintf('%d-%02d', $today->year, $m), 'due' => (int) $month->sum('amount'), 'paid' => (int) $month->sum('paid')];
            })->all(),
            'funds' => Account::query()->where('kind', '!=', 'asset')->orderBy('code')->get()
                ->map(fn (Account $a) => ['name' => $a->name, 'balance' => $this->ledger->balance($a)])
                ->filter(fn (array $f) => $f['balance'] > 0)->sortByDesc('balance')->values()->all(),
            'totals' => [
                'collected' => (int) $payments->filter(fn (Payment $p) => $p->received_at->gte($yearStart))->sum('amount'),
                'paid_out' => (int) $payouts->filter(fn (Payout $p) => $p->paid_at->gte($yearStart))->sum('amount'),
                'due_so_far' => (int) $falling->sum('amount'),
                'paid_so_far' => (int) $falling->sum('paid'),
            ],
        ];
    }

    /**
     * The numbers the AGM needs for the year.
     *
     * @return array<string, mixed>
     */
    public function annual(Group $group, int $year): array
    {
        $from = CarbonImmutable::create($year, 1, 1);
        $to = CarbonImmutable::create($year, 12, 31);
        $payments = Payment::query()->succeeded()->whereBetween('received_at', [$from->startOfDay(), $to->endOfDay()])->get();
        $payouts = Payout::query()->where('status', 'paid')->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])->get();

        return [
            'year' => $year,
            'group' => $group->name,
            'members' => [
                'active' => Member::query()->active()->count(),
                'joined' => Member::query()->whereYear('joined_on', $year)->count(),
                'left' => Member::query()->whereIn('status', ['exited', 'deceased'])->whereYear('exited_on', $year)->count(),
            ],
            'collected' => [
                'total' => (int) $payments->sum('amount'),
                'by_purpose' => $payments->groupBy('purpose')->map(fn ($g) => (int) $g->sum('amount'))->all(),
                'by_channel' => $payments->groupBy('channel')->map(fn ($g) => (int) $g->sum('amount'))->all(),
            ],
            'paid_out' => [
                'total' => (int) $payouts->sum('amount'),
                'by_type' => $payouts->groupBy('type')->map(fn ($g) => (int) $g->sum('amount'))->all(),
            ],
            'funds' => $this->fundMovements($from, $to),
            'projects' => Project::query()->get()->map(fn (Project $p) => ['name' => $p->name, 'status' => $p->status, ...$p->profitAndLoss()])->all(),
            'fundraisers' => Fundraiser::query()->whereYear('starts_on', $year)->get()->map(fn (Fundraiser $f) => ['title' => $f->title, 'target' => $f->target, 'raised' => $f->raised(), 'status' => $f->status])->all(),
            'welfare' => [
                'cases' => WelfareCase::query()->whereYear('event_on', $year)->count(),
                'paid' => (int) $payouts->where('type', 'welfare')->sum('amount'),
            ],
            'meetings' => [
                'held' => Meeting::query()->where('status', 'held')->whereYear('scheduled_at', $year)->count(),
            ],
            'arrears' => (int) Due::query()->overdue()->get()->sum(fn (Due $d) => $d->outstanding()),
        ];
    }
}
