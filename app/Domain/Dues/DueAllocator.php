<?php

namespace App\Domain\Dues;

use App\Domain\Dues\Models\Due;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Models\Account;
use App\Domain\Members\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * Applies money a member has paid to what they owe, oldest first. Anything
 * left over is held as a prepayment and used when the next dues are raised.
 */
final class DueAllocator
{
    private const COMPONENT_ACCOUNTS = [
        'mgr' => ChartOfAccounts::MGR,
        'welfare' => ChartOfAccounts::WELFARE,
        'savings' => ChartOfAccounts::SAVINGS,
        'group' => ChartOfAccounts::GROUP_FUND,
        'assets' => ChartOfAccounts::ASSETS,
    ];

    public function __construct(
        private readonly ChartOfAccounts $accounts,
        private readonly Ledger $ledger,
    ) {}

    /**
     * Settle open dues with $amount and return the credit lines to post,
     * including a prepayment line for any surplus. Updates the dues.
     *
     * @return list<array{account: Account, credit: int, member_id: int|null, memo: string}>
     */
    public function settle(Member $member, int $amount): array
    {
        $credits = [];
        $remaining = $amount;

        $dues = Due::query()
            ->where('member_id', $member->id)
            ->open()
            ->orderBy('due_on')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($dues as $due) {
            if ($remaining <= 0) {
                break;
            }

            $apply = min($remaining, $due->outstanding());

            if ($apply <= 0) {
                continue;
            }

            foreach (Due::split($due->breakdown, $due->paid, $due->paid + $apply) as $component => $cents) {
                $credits[] = $this->creditLine($member, $component, $cents, $due->description);
            }

            $due->paid += $apply;
            $due->status = $due->paid >= $due->amount ? 'paid' : 'open';
            $due->save();
            $remaining -= $apply;
        }

        if ($remaining > 0) {
            $credits[] = [
                'account' => $this->accounts->get($member->group_id, ChartOfAccounts::PREPAYMENTS),
                'credit' => $remaining,
                'member_id' => $member->id,
                'memo' => 'Paid in advance',
            ];
        }

        return $this->merge($credits);
    }

    /**
     * Use a member's prepayment balance against their open dues.
     */
    public function applyPrepayment(Member $member): int
    {
        return DB::transaction(function () use ($member) {
            $prepayments = $this->accounts->get($member->group_id, ChartOfAccounts::PREPAYMENTS);
            $available = $this->ledger->balance($prepayments, $member->id);
            $owed = (int) Due::query()->where('member_id', $member->id)->open()->get()->sum(fn (Due $d) => $d->outstanding());
            $use = min($available, $owed);

            if ($use <= 0) {
                return 0;
            }

            $credits = array_filter(
                $this->settle($member, $use),
                fn ($line) => $line['account']->code !== ChartOfAccounts::PREPAYMENTS,
            );

            $this->ledger->post(
                "Prepayment applied for {$member->name}",
                [
                    ['account' => $prepayments, 'debit' => $use, 'member_id' => $member->id, 'memo' => 'Prepayment used'],
                    ...array_values($credits),
                ],
                $member,
            );

            return $use;
        });
    }

    /**
     * Clear what a member owes (up to today) out of their own savings, after
     * first folding any prepayment into savings. Used on exit and share-out.
     */
    public function payFromSavings(Member $member, string $why): int
    {
        return DB::transaction(function () use ($member, $why) {
            $prepayments = $this->accounts->get($member->group_id, ChartOfAccounts::PREPAYMENTS);
            $savings = $this->accounts->get($member->group_id, ChartOfAccounts::SAVINGS);

            $prepaid = $this->ledger->balance($prepayments, $member->id);
            if ($prepaid > 0) {
                $this->ledger->post("Prepayment moved to savings for {$member->name}", [
                    ['account' => $prepayments, 'debit' => $prepaid, 'member_id' => $member->id, 'memo' => $why],
                    ['account' => $savings, 'credit' => $prepaid, 'member_id' => $member->id, 'memo' => $why],
                ], $member);
            }

            $owed = (int) Due::query()->where('member_id', $member->id)->open()
                ->whereDate('due_on', '<=', now()->toDateString())
                ->get()->sum(fn (Due $d) => $d->outstanding());
            $use = min($owed, $this->ledger->balance($savings, $member->id));

            if ($use <= 0) {
                return 0;
            }

            $credits = $this->settle($member, $use);
            $this->ledger->post("Debts cleared from savings for {$member->name}", [
                ['account' => $savings, 'debit' => $use, 'member_id' => $member->id, 'memo' => "{$why}: debts cleared"],
                ...$credits,
            ], $member);

            return $use;
        });
    }

    /**
     * @return array{account: Account, credit: int, member_id: int|null, memo: string}
     */
    private function creditLine(Member $member, string $component, int $cents, string $memo): array
    {
        $account = $this->accounts->get($member->group_id, self::COMPONENT_ACCOUNTS[$component] ?? ChartOfAccounts::GROUP_FUND);

        // Every line carries the member so their statement can show where
        // each shilling went, not only the per-member savings.
        return [
            'account' => $account,
            'credit' => $cents,
            'member_id' => $member->id,
            'memo' => $memo,
        ];
    }

    /**
     * Combine lines that hit the same account so entries stay readable.
     *
     * @param  list<array{account: Account, credit: int, member_id: int|null, memo: string}>  $lines
     * @return list<array{account: Account, credit: int, member_id: int|null, memo: string}>
     */
    private function merge(array $lines): array
    {
        $merged = [];

        foreach ($lines as $line) {
            $key = $line['account']->id.':'.($line['member_id'] ?? '');

            if (isset($merged[$key])) {
                $merged[$key]['credit'] += $line['credit'];
                if (! str_contains($merged[$key]['memo'], $line['memo'])) {
                    $merged[$key]['memo'] .= '; '.$line['memo'];
                }
            } else {
                $merged[$key] = $line;
            }
        }

        return array_values($merged);
    }
}
