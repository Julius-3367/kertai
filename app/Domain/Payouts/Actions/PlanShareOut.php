<?php

namespace App\Domain\Payouts\Actions;

use App\Domain\Dues\DueAllocator;
use App\Domain\Dues\Models\Due;
use App\Domain\Groups\Models\Group;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Loans\LoanBook;
use App\Domain\Loans\Models\Loan;
use App\Domain\Members\MemberFinance;
use App\Domain\Members\Models\Member;
use App\Domain\Payouts\Models\Payout;
use App\Support\RuleViolation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * December share-out. Each member gets their savings back, less anything
 * they owe, plus (if the meeting agrees) a share of the group fund in
 * proportion to their savings. Every member's payout still needs the two
 * approvals.
 */
final class PlanShareOut
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly ChartOfAccounts $accounts,
        private readonly MemberFinance $finance,
        private readonly RequestPayout $requests,
        private readonly DueAllocator $dues,
        private readonly LoanBook $loans,
    ) {}

    /**
     * @return array{group_fund: int, distribute: int, rows: array<int, array<string, mixed>>}
     */
    public function preview(Group $group, int $groupFundPercent): array
    {
        $groupFund = $this->ledger->balance($this->accounts->get($group, ChartOfAccounts::GROUP_FUND));
        $distribute = intdiv(max(0, $groupFund) * max(0, min(100, $groupFundPercent)), 100);

        $members = Member::query()->active()->orderBy('member_number')->get();
        $savings = $members->mapWithKeys(fn (Member $m) => [$m->id => $this->finance->savings($m)]);
        $totalSavings = max(0, (int) $savings->filter(fn ($s) => $s > 0)->sum());

        $rows = $members->map(function (Member $m) use ($savings, $totalSavings, $distribute) {
            $saved = max(0, (int) $savings[$m->id]);
            $owed = $this->finance->arrears($m) + $this->dueToday($m) + $this->loanOwed($m);
            $bonus = $totalSavings > 0 ? intdiv($distribute * $saved, $totalSavings) : 0;

            return [
                'member_id' => $m->id,
                'name' => $m->name,
                'member_number' => $m->member_number,
                'savings' => $saved,
                'owed' => $owed,
                'from_savings' => max(0, $saved - $owed),
                'from_group_fund' => $bonus,
                'total' => max(0, $saved - $owed) + $bonus,
            ];
        })->values()->all();

        return ['group_fund' => $groupFund, 'distribute' => $distribute, 'rows' => $rows];
    }

    /**
     * @return array{batch: string, count: int}
     */
    public function raise(Group $group, int $groupFundPercent, string $label): array
    {
        $plan = $this->preview($group, $groupFundPercent);
        $batch = 'share-out-'.now()->format('Y').'-'.Str::lower(Str::random(4));

        if (Payout::query()->where('type', 'share_out')->whereIn('status', ['pending', 'approved'])->exists()) {
            throw new RuleViolation('A share-out is already waiting for approval or payment.');
        }

        $count = DB::transaction(function () use ($group, $plan, $batch, $label) {
            $savingsAccount = $this->accounts->get($group, ChartOfAccounts::SAVINGS);
            $groupFund = $this->accounts->get($group, ChartOfAccounts::GROUP_FUND);
            $count = 0;

            foreach ($plan['rows'] as $row) {
                $member = Member::findOrFail((int) $row['member_id']);

                // Debts and loans come out of savings first; what is left is paid out.
                $this->dues->payFromSavings($member, $label);
                $this->loans->recoverFromOwnSavings($member, $label);
                $fromSavings = $this->ledger->balance($savingsAccount, $member->id);

                if ($fromSavings > 0) {
                    $this->requests->handle($group, 'share_out', $fromSavings, $savingsAccount, "{$label}: savings", $member, batch: $batch, notifyApprovers: false, raisedByRules: true);
                    $count++;
                }

                if ($row['from_group_fund'] > 0) {
                    $this->requests->handle($group, 'share_out', $row['from_group_fund'], $groupFund, "{$label}: share of group fund", $member, batch: $batch, notifyApprovers: false, raisedByRules: true);
                    $count++;
                }
            }

            return $count;
        });

        if ($count === 0) {
            throw new RuleViolation('Nobody has anything to share out.');
        }

        return ['batch' => $batch, 'count' => $count];
    }

    private function loanOwed(Member $member): int
    {
        $loans = Loan::query()->where('member_id', $member->id)->active()->pluck('id')->all();

        return (int) collect($this->loans->balances($loans))->sum('total');
    }

    private function dueToday(Member $member): int
    {
        return (int) Due::query()->where('member_id', $member->id)->open()
            ->whereDate('due_on', now()->toDateString())
            ->get()->sum(fn (Due $d) => $d->outstanding());
    }
}
