<?php

namespace App\Domain\Dues\Actions;

use App\Domain\Dues\Models\Due;
use App\Domain\Groups\Models\Group;
use App\Domain\Notifications\Notifier;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Flat fine, once per late contribution, after the grace period.
 */
final class ApplyLateFines
{
    public function __construct(private readonly Notifier $notifier) {}

    public function handle(Group $group, ?CarbonImmutable $today = null): int
    {
        $settings = $group->settings();
        $fine = (int) $settings->get('late_fine_amount');

        if ($fine <= 0) {
            return 0;
        }

        $cutoff = ($today ?? CarbonImmutable::today())->subDays((int) $settings->get('late_fine_grace_days'));
        $start = $settings->duesStartOn();
        $count = 0;

        $late = Due::withoutGlobalScopes()
            ->with('member')
            ->where('group_id', $group->id)
            ->where('type', 'contribution')
            ->where('status', 'open')
            ->whereNull('fined_at')
            ->whereDate('due_on', '<', $cutoff->toDateString())
            ->when($start, fn ($q) => $q->whereDate('due_on', '>=', $start->toDateString()))
            ->get();

        foreach ($late as $due) {
            DB::transaction(function () use ($group, $due, $fine, &$count) {
                Due::withoutGlobalScopes()->create([
                    'group_id' => $group->id,
                    'member_id' => $due->member_id,
                    'type' => 'fine',
                    'period' => $due->period,
                    'description' => "Late fine: {$due->description}",
                    'amount' => $fine,
                    'breakdown' => ['group' => $fine],
                    'due_on' => CarbonImmutable::today()->toDateString(),
                    'source_type' => $due->getMorphClass(),
                    'source_id' => $due->id,
                ]);

                $due->forceFill(['fined_at' => now()])->save();
                $count++;
            });

            $this->notifier->toMember($due->member, 'fine_applied', [
                'amount' => Money::format($fine),
                'reason' => $due->description,
            ]);
        }

        return $count;
    }
}
