<?php

namespace App\Domain\Loans;

use App\Domain\Groups\Models\Group;
use App\Domain\Groups\Support\GroupSettings;
use App\Domain\Loans\Models\Loan;
use App\Domain\Members\MemberFinance;
use App\Domain\Members\Models\Member;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * The group's lending rules, checked one by one so a member can see exactly
 * which they meet and which they do not.
 */
final class LoanEligibility
{
    public function __construct(
        private readonly MemberFinance $finance,
        private readonly LoanBook $book,
    ) {}

    /**
     * @return list<array{key: string, ok: bool, label: string}>
     */
    public function check(Member $member, ?int $amount = null, ?int $termMonths = null, ?Loan $ignore = null): array
    {
        $settings = $this->settings($member);
        $savings = $this->finance->savings($member);
        $months = (int) floor($member->joined_on->diffInMonths(CarbonImmutable::today()));
        $minMonths = (int) $settings->get('loan_min_membership_months');
        $minSavings = (int) $settings->get('loan_min_savings');
        $max = $this->maxAmount($member);
        $maxTerm = (int) $settings->get('loan_max_term_months');

        $rules = [
            [
                'key' => 'enabled',
                'ok' => $settings->loansEnabled(),
                'label' => $settings->loansEnabled() ? 'The group is giving loans' : 'The group is not giving new loans at the moment',
            ],
            ['key' => 'active', 'ok' => $member->isActive(), 'label' => 'Active member'],
            [
                'key' => 'membership',
                'ok' => $months >= $minMonths,
                'label' => "Member for at least {$minMonths} months (you have {$months})",
            ],
            [
                'key' => 'savings',
                'ok' => $savings >= $minSavings,
                'label' => 'Savings of at least '.Money::format($minSavings).' (you have '.Money::format($savings).')',
            ],
        ];

        if ($settings->get('loan_block_if_arrears')) {
            $arrears = $this->finance->arrears($member);
            $rules[] = [
                'key' => 'arrears',
                'ok' => $arrears === 0,
                'label' => $arrears === 0 ? 'No contribution arrears' : 'Clear contribution arrears of '.Money::format($arrears).' first',
            ];
        }

        $running = Loan::query()->where('member_id', $member->id)->open()->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists();
        $rules[] = ['key' => 'one_loan', 'ok' => ! $running, 'label' => $running ? 'Finish your current loan first' : 'No other loan running'];

        if ($amount !== null) {
            $rules[] = [
                'key' => 'amount',
                'ok' => $amount > 0 && ($max === null || $amount <= $max),
                'label' => $max === null ? 'Amount above zero' : 'Up to '.Money::format($max).' ('.$settings->get('loan_savings_multiple').' times your savings)',
            ];
        }

        if ($termMonths !== null) {
            $rules[] = [
                'key' => 'term',
                'ok' => $termMonths >= 1 && $termMonths <= $maxTerm,
                'label' => "Repaid within {$maxTerm} months",
            ];
        }

        return $rules;
    }

    /**
     * @param  list<array{key: string, ok: bool, label: string}>  $rules
     */
    public static function passes(array $rules): bool
    {
        return collect($rules)->every(fn ($r) => $r['ok']);
    }

    /** The most this member may borrow, or null when savings set no cap. */
    public function maxAmount(Member $member): ?int
    {
        $multiple = (float) $this->settings($member)->get('loan_savings_multiple');

        return $multiple > 0 ? (int) floor(max(0, $this->finance->savings($member)) * $multiple) : null;
    }

    /**
     * Savings a member can still pledge as guarantor: what they have saved
     * less what they already stand behind.
     */
    public function freeSavings(Member $member): int
    {
        return max(0, $this->finance->savings($member) - $this->book->guaranteed($member));
    }

    /**
     * How much of the loan guarantors must cover: whatever the borrower's
     * own savings do not.
     */
    public function coverNeeded(Member $borrower, int $amount): int
    {
        return max(0, $amount - $this->freeSavings($borrower));
    }

    public function guarantorsRequired(Member $member): int
    {
        return (int) $this->settings($member)->get('loan_guarantors_required');
    }

    private function settings(Member $member): GroupSettings
    {
        return Group::findOrFail($member->group_id)->settings();
    }
}
