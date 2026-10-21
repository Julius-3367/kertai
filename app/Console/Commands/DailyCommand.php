<?php

namespace App\Console\Commands;

use App\Domain\Documents\Models\ComplianceItem;
use App\Domain\Dues\Actions\ApplyLateFines;
use App\Domain\Dues\Actions\GenerateMonthlyDues;
use App\Domain\Dues\Models\Due;
use App\Domain\Groups\CurrentGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Notifier;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * The once-a-day jobs for every group: raise this month's dues on the 1st,
 * remind members before the due day, charge late fines, nudge members in
 * arrears weekly, and remind officials of compliance deadlines.
 */
class DailyCommand extends Command
{
    protected $signature = 'kertai:daily {--date= : Pretend today is this date (YYYY-MM-DD)}';

    protected $description = 'Raise dues, send reminders, apply late fines and compliance reminders';

    public function handle(GenerateMonthlyDues $dues, ApplyLateFines $fines, Notifier $notifier, CurrentGroup $current): int
    {
        $today = $this->option('date') ? CarbonImmutable::parse($this->option('date')) : CarbonImmutable::today();

        foreach (Group::all() as $group) {
            $current->runAs($group, function () use ($group, $today, $dues, $fines, $notifier) {
                $settings = $group->settings();

                $raised = $dues->handle($group, $today->format('Y-m'));
                $this->line("{$group->name}: {$raised} dues raised for {$today->format('M Y')}");

                $dueDay = min((int) $settings->get('contribution_due_day'), $today->daysInMonth);
                $remindOn = $today->setDay($dueDay)->subDays((int) $settings->get('reminder_days_before'));

                if ($today->isSameDay($remindOn)) {
                    $this->remindDue($group, $today, $notifier);
                }

                $fined = $fines->handle($group, $today);
                $this->line("{$group->name}: {$fined} late fines");

                if ($today->isMonday()) {
                    $this->remindArrears($group, $today, $notifier);
                }

                $this->remindCompliance($group, $today, $notifier);
            });
        }

        return self::SUCCESS;
    }

    private function remindDue(Group $group, CarbonImmutable $today, Notifier $notifier): void
    {
        $open = Due::query()->where('type', 'contribution')->where('period', $today->format('Y-m'))->open()->with('member')->get();

        foreach ($open as $due) {
            $notifier->toMember($due->member, 'contribution_reminder', [
                'period' => $today->format('F'),
                'amount' => Money::format($due->outstanding()),
                'due_date' => $due->due_on->format('j M'),
            ]);
        }

        $this->line("{$group->name}: {$open->count()} contribution reminders");
    }

    private function remindArrears(Group $group, CarbonImmutable $today, Notifier $notifier): void
    {
        $members = Member::query()->active()->whereIn('id', Due::query()->overdue($today)->select('member_id'))->get();

        foreach ($members as $member) {
            $notifier->toMember($member, 'arrears_reminder');
        }

        $this->line("{$group->name}: {$members->count()} arrears reminders");
    }

    private function remindCompliance(Group $group, CarbonImmutable $today, Notifier $notifier): void
    {
        $items = ComplianceItem::query()
            ->where('status', 'pending')
            ->whereDate('due_on', '<=', $today->addDays(14)->toDateString())
            ->where(fn ($q) => $q->whereNull('reminded_at')->orWhere('reminded_at', '<', $today->subDays(7)))
            ->with('position')
            ->get();

        foreach ($items as $item) {
            $holders = $item->position
                ? $item->position->terms()->current($today)->with('member')->get()->pluck('member')
                : collect();

            foreach ($holders as $member) {
                $notifier->toMember($member, 'compliance_due', [
                    'title' => $item->title,
                    'date' => $item->due_on->format('j M Y'),
                ]);
            }

            $item->update(['reminded_at' => now()]);
        }
    }
}
