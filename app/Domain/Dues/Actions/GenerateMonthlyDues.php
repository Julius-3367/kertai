<?php

namespace App\Domain\Dues\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Dues\DueAllocator;
use App\Domain\Dues\Models\Due;
use App\Domain\Groups\Models\Group;
use App\Domain\Members\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Raises the monthly contribution for every active member. Safe to run more
 * than once for the same month: members who already have the due are skipped.
 */
final class GenerateMonthlyDues
{
    public function __construct(private readonly DueAllocator $allocator) {}

    public function handle(Group $group, string $period): int
    {
        $month = CarbonImmutable::createFromFormat('Y-m-d', "{$period}-01")->startOfDay();
        $settings = $group->settings();
        $dueDay = min((int) $settings->get('contribution_due_day'), $month->daysInMonth);
        $dueOn = $month->setDay($dueDay);
        $breakdown = $settings->contributionBreakdown();
        $amount = array_sum($breakdown);
        $created = 0;
        $start = $settings->duesStartOn();

        if ($amount <= 0 || ($start && $month->endOfMonth()->lt($start))) {
            return 0;
        }

        if ($start && $dueOn->lt($start)) {
            $dueOn = $start;
        }

        $members = Member::withoutGlobalScopes()
            ->where('group_id', $group->id)
            ->active()
            ->whereDate('joined_on', '<=', $month->endOfMonth()->toDateString())
            ->get();

        foreach ($members as $member) {
            DB::transaction(function () use ($group, $member, $period, $month, $dueOn, $breakdown, $amount, &$created) {
                $exists = Due::withoutGlobalScopes()
                    ->where('member_id', $member->id)
                    ->where('type', 'contribution')
                    ->where('period', $period)
                    ->exists();

                if ($exists) {
                    return;
                }

                Due::withoutGlobalScopes()->create([
                    'group_id' => $group->id,
                    'member_id' => $member->id,
                    'type' => 'contribution',
                    'period' => $period,
                    'description' => 'Contribution '.$month->format('M Y'),
                    'amount' => $amount,
                    'breakdown' => $breakdown,
                    'due_on' => $dueOn->toDateString(),
                ]);

                $this->allocator->applyPrepayment($member);
                $created++;
            });
        }

        if ($created > 0) {
            Audit::log('dues.generated', "Generated {$created} contribution dues for {$month->format('F Y')}", null, ['period' => $period], $group->id);
        }

        return $created;
    }
}
