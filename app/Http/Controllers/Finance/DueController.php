<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Access\Permission;
use App\Domain\Audit\Audit;
use App\Domain\Dues\Actions\ApplyLateFines;
use App\Domain\Dues\Actions\GenerateMonthlyDues;
use App\Domain\Dues\Models\Due;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Notifier;
use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DueController extends Controller
{
    public function index(Request $request): Response
    {
        $period = $request->string('period')->toString() ?: CarbonImmutable::today()->format('Y-m');
        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();

        $dues = Due::query()
            ->with('member:id,name,member_number')
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($status === 'overdue', fn ($q) => $q->overdue(), fn ($q) => $q->when($status !== '', fn ($q) => $q->where('status', $status)))
            ->when($type === 'contribution' || $type === '', fn ($q) => $q->where(fn ($w) => $w->where('period', $period)->orWhere('type', '!=', 'contribution')))
            ->orderBy('due_on')
            ->orderBy('member_id')
            ->get();

        $periodDues = Due::query()->where('type', 'contribution')->where('period', $period)->get();

        return Inertia::render('dues/index', [
            'dues' => $dues->map(fn (Due $d) => [
                'id' => $d->id,
                'member' => $d->member->name,
                'member_id' => $d->member_id,
                'member_number' => $d->member->member_number,
                'type' => $d->type,
                'period' => $d->period,
                'description' => $d->description,
                'amount' => $d->amount,
                'paid' => $d->paid,
                'due_on' => $d->due_on->toDateString(),
                'status' => $d->status,
                'overdue' => $d->status === 'open' && $d->due_on->isPast(),
                'waived_reason' => $d->waived_reason,
            ]),
            'summary' => [
                'expected' => (int) $periodDues->sum('amount'),
                'collected' => (int) $periodDues->sum('paid'),
                'members_paid' => $periodDues->where('status', 'paid')->count(),
                'members_total' => $periodDues->count(),
                'arrears_total' => (int) Due::query()->overdue()->get()->sum(fn (Due $d) => $d->outstanding()),
            ],
            'filters' => ['period' => $period, 'type' => $type, 'status' => $status],
            'members' => $this->memberOptions(),
            'contribution' => $this->group()->settings()->contributionBreakdown(),
        ]);
    }

    public function generate(Request $request, GenerateMonthlyDues $generate): RedirectResponse
    {
        $data = $request->validate(['period' => ['required', 'date_format:Y-m']]);
        $count = $generate->handle($this->group(), $data['period']);

        $this->toast($count > 0
            ? "Raised {$count} contributions for ".CarbonImmutable::parse($data['period'].'-01')->format('F Y').'.'
            : 'Every active member already has a contribution for that month.', $count > 0 ? 'success' : 'info');

        return to_route('dues.index', ['period' => $data['period']]);
    }

    public function applyFines(ApplyLateFines $fines): RedirectResponse
    {
        $count = $fines->handle($this->group());
        $this->toast($count > 0 ? "Charged {$count} late fines." : 'No late contributions need a fine.', $count > 0 ? 'success' : 'info');

        return back();
    }

    public function storeFine(Request $request, Notifier $notifier): RedirectResponse
    {
        $data = $request->validate([
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
            'amount' => Money::rule(),
            'reason' => ['required', 'string', 'max:150'],
        ]);

        $member = Member::findOrFail($data['member_id']);
        $amount = $this->cents($data['amount']);

        $due = Due::create([
            'member_id' => $member->id,
            'type' => 'fine',
            'description' => "Fine: {$data['reason']}",
            'amount' => $amount,
            'breakdown' => ['group' => $amount],
            'due_on' => CarbonImmutable::today()->toDateString(),
        ]);

        Audit::log('fine.charged', "Fined {$member->name} ".Money::format($amount).": {$data['reason']}", $due);
        $notifier->toMember($member, 'fine_applied', ['amount' => Money::format($amount), 'reason' => $data['reason']]);
        $this->toast('Fine of '.Money::format($amount)." charged to {$member->name}.");

        return back();
    }

    public function waive(Request $request, Due $due): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:150']]);

        $permission = $due->type === 'fine' ? Permission::FinesWaive : Permission::SettingsManage;
        Gate::authorize($permission->value);

        if ($due->status !== 'open') {
            throw new RuleViolation('Only open items can be waived.');
        }

        $due->update(['status' => 'waived', 'waived_by' => $request->user()->id, 'waived_reason' => $data['reason']]);
        Audit::log('due.waived', "Waived {$due->description} for {$due->member->name}: {$data['reason']}", $due, ['outstanding' => $due->amount - $due->paid]);
        $this->toast('Waived.');

        return back();
    }
}
