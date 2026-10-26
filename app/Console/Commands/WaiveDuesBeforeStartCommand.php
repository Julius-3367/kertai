<?php

namespace App\Console\Commands;

use App\Domain\Audit\Audit;
use App\Domain\Dues\Models\Due;
use App\Domain\Groups\Models\Group;
use App\Support\Money;
use Illuminate\Console\Command;

/**
 * Clears contributions and fines that the daily job raised before the group
 * started using Kertai (the dues_start_on rule). They are waived, not
 * deleted, so the history still shows them.
 */
class WaiveDuesBeforeStartCommand extends Command
{
    protected $signature = 'kertai:waive-before-start {--dry-run : List what would be waived without changing anything}';

    protected $description = 'Waive open dues that fall before the group\'s start date';

    public function handle(): int
    {
        foreach (Group::all() as $group) {
            $start = $group->settings()->duesStartOn();

            if ($start === null) {
                $this->line("{$group->name}: no start date set, skipped");

                continue;
            }

            $dues = Due::withoutGlobalScopes()
                ->with('member')
                ->where('group_id', $group->id)
                ->where('status', 'open')
                ->whereDate('due_on', '<', $start->toDateString())
                ->orderBy('member_id')
                ->get();

            foreach ($dues as $due) {
                $this->line(sprintf('  %-28s %-34s %s', $due->member->name, $due->description, Money::format($due->outstanding())));
            }

            if ($this->option('dry-run') || $dues->isEmpty()) {
                $this->info("{$group->name}: {$dues->count()} open dues before {$start->format('j M Y')}".($this->option('dry-run') ? ' (dry run, nothing changed)' : ''));

                continue;
            }

            $reason = 'Before the group started using Kertai on '.$start->format('j M Y');

            Due::withoutGlobalScopes()
                ->whereIn('id', $dues->pluck('id'))
                ->update(['status' => 'waived', 'waived_reason' => $reason]);

            Audit::log('dues.waived_before_start', "Waived {$dues->count()} dues raised before {$start->format('j M Y')}", null, [
                'due_ids' => $dues->pluck('id')->all(),
                'outstanding' => $dues->sum(fn (Due $d) => $d->outstanding()),
            ], $group->id);

            $this->info("{$group->name}: waived {$dues->count()} dues");
        }

        return self::SUCCESS;
    }
}
