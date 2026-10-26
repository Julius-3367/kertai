<?php

namespace App\Domain\Groups\Support;

use Carbon\CarbonImmutable;

/**
 * Group rules. Money values are cents. Every default here is the "standard
 * way" agreed for launch and can be changed from Settings, where the change
 * is recorded against a resolution number.
 */
final class GroupSettings
{
    public const DEFAULTS = [
        'contribution_amount' => 100000,
        'mgr_share' => 50000,
        'welfare_share' => 20000,
        'contribution_due_day' => 5,
        'dues_start_on' => null,
        'late_fine_amount' => 10000,
        'late_fine_grace_days' => 0,
        'reminder_days_before' => 2,
        'exit_notice_days' => 30,
        'exit_refund_days' => 30,
        'payout_approvals_required' => 2,
        'mgr_skip_members_in_arrears' => true,
        'show_contributor_names_by_default' => false,
        'payer_pays_mpesa_fees' => true,
        'sms_costs_from_group_fund' => true,
        'project_profit_basis' => 'contribution',
        'share_out_month' => 12,
        'loans_enabled' => false,
        'sms_sender_id' => 'KERTAI006',
        'welfare_benefits' => [
            'member_death' => 5000000,
            'spouse_death' => 3000000,
            'child_death' => 2000000,
            'parent_death' => 2000000,
            'hospitalisation' => 1000000,
        ],
        'resolution_reference' => null,
    ];

    /** @param  array<string, mixed>  $values */
    public function __construct(private array $values) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->values, $key, data_get(self::DEFAULTS, $key, $default));
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_replace_recursive(self::DEFAULTS, $this->values);
    }

    /**
     * The day the group started keeping its books in Kertai. Months before it
     * are not charged, and the first month falls due on this day at the earliest.
     */
    public function duesStartOn(): ?CarbonImmutable
    {
        $date = $this->get('dues_start_on');

        return $date ? CarbonImmutable::parse($date)->startOfDay() : null;
    }

    public function contributionAmount(): int
    {
        return (int) $this->get('contribution_amount');
    }

    /**
     * How one contribution splits across funds, in the order money is
     * applied: merry-go-round first, then welfare, then savings.
     *
     * @return array{mgr: int, welfare: int, savings: int}
     */
    public function contributionBreakdown(): array
    {
        $total = $this->contributionAmount();
        $mgr = min((int) $this->get('mgr_share'), $total);
        $welfare = min((int) $this->get('welfare_share'), $total - $mgr);

        return ['mgr' => $mgr, 'welfare' => $welfare, 'savings' => $total - $mgr - $welfare];
    }

    public function welfareBenefit(string $event, string $relationship): int
    {
        $key = match (true) {
            $event === 'death' && $relationship === 'self' => 'member_death',
            $event === 'death' => "{$relationship}_death",
            $event === 'hospitalisation' => 'hospitalisation',
            default => null,
        };

        return $key ? (int) $this->get("welfare_benefits.{$key}", 0) : 0;
    }
}
