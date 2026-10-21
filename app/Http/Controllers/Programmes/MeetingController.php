<?php

namespace App\Http\Controllers\Programmes;

use App\Domain\Access\Permission;
use App\Domain\Audit\Audit;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Models\MeetingAttendance;
use App\Domain\Meetings\Models\Resolution;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Notifier;
use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use App\Support\Money;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('meetings/index', [
            'meetings' => Meeting::query()->withCount(['attendances as present_count' => fn ($q) => $q->whereIn('status', ['present', 'late'])])
                ->latest('scheduled_at')->get()->map(fn (Meeting $m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'kind' => $m->kind,
                    'scheduled_at' => $m->scheduled_at->toIso8601String(),
                    'venue' => $m->venue,
                    'status' => $m->status,
                    'present' => $m->present_count,
                ]),
            'resolutions' => Resolution::query()->latest('adopted_on')->limit(50)->get()->map(fn (Resolution $r) => [
                'id' => $r->id,
                'number' => $r->number,
                'title' => $r->title,
                'adopted_on' => $r->adopted_on->toDateString(),
                'meeting_id' => $r->meeting_id,
            ]),
            'kinds' => Meeting::KINDS,
            'canManage' => request()->user()->hasPermission(Permission::MeetingsManage->value),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::MeetingsManage->value);

        $meeting = Meeting::create([...$this->validated($request), 'created_by' => $request->user()->id]);
        Audit::log('meeting.created', "Scheduled {$meeting->title}", $meeting);
        $this->toast('Meeting scheduled. Send the notice from the meeting page.');

        return to_route('meetings.show', $meeting);
    }

    public function show(Request $request, Meeting $meeting): Response
    {
        $attendance = $meeting->attendances()->pluck('status', 'member_id');

        return Inertia::render('meetings/show', [
            'meeting' => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'kind' => $meeting->kind,
                'scheduled_at' => $meeting->scheduled_at->format('Y-m-d\TH:i'),
                'venue' => $meeting->venue,
                'agenda' => $meeting->agenda,
                'minutes' => $meeting->minutes,
                'status' => $meeting->status,
            ],
            'roll' => Member::query()->active()->orderBy('name')->get()->map(fn (Member $m) => [
                'member_id' => $m->id,
                'name' => $m->name,
                'member_number' => $m->member_number,
                'status' => $attendance[$m->id] ?? null,
            ]),
            'resolutions' => $meeting->resolutions->map(fn (Resolution $r) => [
                'id' => $r->id,
                'number' => $r->number,
                'title' => $r->title,
                'body' => $r->body,
                'adopted_on' => $r->adopted_on->toDateString(),
            ]),
            'collections' => $request->user()->hasPermission(Permission::FinanceView->value)
                ? Payment::query()->where('meeting_id', $meeting->id)->with('member:id,name')->get()->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'member' => $p->member?->name ?? $p->payer_name,
                    'amount' => $p->amount,
                    'purpose' => $p->purpose,
                ])
                : [],
            'kinds' => Meeting::KINDS,
            'canManage' => $request->user()->hasPermission(Permission::MeetingsManage->value),
            'canCollect' => $request->user()->hasPermission(Permission::PaymentsRecord->value),
            'nextResolutionNumber' => $this->nextResolutionNumber(),
        ]);
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        Gate::authorize(Permission::MeetingsManage->value);

        $data = $this->validated($request);
        $data += $request->validate([
            'minutes' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', Rule::in(['scheduled', 'held', 'cancelled'])],
        ]);

        $meeting->update($data);
        Audit::log('meeting.updated', "Updated {$meeting->title}", $meeting);
        $this->toast('Meeting saved.');

        return back();
    }

    public function attendance(Request $request, Meeting $meeting): RedirectResponse
    {
        Gate::authorize(Permission::MeetingsManage->value);

        $data = $request->validate([
            'attendance' => ['required', 'array'],
            'attendance.*' => ['nullable', Rule::in(MeetingAttendance::STATUSES)],
        ]);

        $memberIds = Member::query()->pluck('id')->all();

        DB::transaction(function () use ($meeting, $data, $memberIds) {
            foreach ($data['attendance'] as $memberId => $status) {
                if (! in_array((int) $memberId, $memberIds, true)) {
                    continue;
                }

                if ($status === null || $status === '') {
                    $meeting->attendances()->where('member_id', $memberId)->delete();

                    continue;
                }

                MeetingAttendance::updateOrCreate(
                    ['meeting_id' => $meeting->id, 'member_id' => (int) $memberId],
                    ['status' => $status],
                );
            }
        });

        $this->toast('Attendance saved.');

        return back();
    }

    public function notify(Meeting $meeting, Notifier $notifier): RedirectResponse
    {
        Gate::authorize(Permission::MeetingsManage->value);

        $count = 0;
        foreach (Member::query()->active()->get() as $member) {
            $count += count($notifier->toMember($member, 'meeting_notice', [
                'title' => $meeting->title,
                'date' => $meeting->scheduled_at->format('D j M, g:ia'),
                'venue' => $meeting->venue ?? 'the usual venue',
            ]));
        }

        Audit::log('meeting.notified', "Notice for {$meeting->title} sent ({$count} messages)", $meeting);
        $this->toast("Meeting notice queued: {$count} messages.");

        return back();
    }

    public function resolution(Request $request, Meeting $meeting): RedirectResponse
    {
        Gate::authorize(Permission::MeetingsManage->value);

        $data = $request->validate([
            'number' => ['required', 'string', 'max:30', Rule::unique('resolutions', 'number')->where('group_id', $this->group()->id)],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
            'adopted_on' => ['required', 'date'],
        ]);

        $resolution = $meeting->resolutions()->create([...$data, 'group_id' => $this->group()->id]);
        Audit::log('resolution.adopted', "Resolution {$resolution->number}: {$resolution->title}", $resolution);
        $this->toast("Resolution {$resolution->number} recorded.");

        return back();
    }

    /**
     * Cash handed in at the meeting, one row per member.
     */
    public function collect(Request $request, Meeting $meeting, RecordPayment $record): RedirectResponse
    {
        Gate::authorize(Permission::PaymentsRecord->value);

        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.member_id' => ['required', 'integer', Rule::exists('members', 'id')->where('group_id', $this->group()->id)],
            'rows.*.amount' => Money::rule(),
        ]);

        $total = 0;
        $date = $meeting->scheduled_at->isFuture() ? CarbonImmutable::now() : $meeting->scheduled_at;

        DB::transaction(function () use ($data, $meeting, $record, $date, &$total) {
            foreach ($data['rows'] as $row) {
                $amount = $this->cents($row['amount']);
                $record->handle($this->group(), 'cash', $amount, 'contribution', Member::findOrFail($row['member_id']), null, null, $date, meetingId: $meeting->id);
                $total += $amount;
            }
        });

        if ($total <= 0) {
            throw new RuleViolation('Nothing was recorded.');
        }

        $this->toast('Recorded '.count($data['rows']).' cash payments totalling '.Money::format($total).'. Remember to bank the cash and record the transfer.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::in(array_keys(Meeting::KINDS))],
            'scheduled_at' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:150'],
            'agenda' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function nextResolutionNumber(): string
    {
        $year = CarbonImmutable::today()->year;
        $count = Resolution::query()->where('number', 'like', "{$year}/%")->count();

        return $year.'/'.str_pad((string) ($count + 1), 2, '0', STR_PAD_LEFT);
    }
}
