<?php

namespace App\Http\Controllers\Programmes;

use App\Domain\Access\Permission;
use App\Domain\Audit\Audit;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Notifier;
use App\Domain\Votes\Models\Poll;
use App\Domain\Votes\Models\PollVote;
use App\Http\Controllers\Controller;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Votes between meetings. Any active member votes once and may change
 * their vote until it closes. Results show to a member once they have
 * voted, and to everyone when the vote closes.
 */
class PollController extends Controller
{
    public function index(Request $request): Response
    {
        $member = $request->user()->member;
        $manager = $request->user()->hasPermission(Permission::MeetingsManage->value);
        $eligible = Member::query()->active()->count();

        $polls = Poll::query()->withCount('votes')->latest()->limit(50)->get()
            ->map(fn (Poll $p) => [
                'id' => $p->id,
                'question' => $p->question,
                'closes_at' => $p->closes_at->toIso8601String(),
                'open' => $p->isOpen(),
                'votes' => $p->votes_count,
                'eligible' => $eligible,
                'voted' => $member !== null && $p->votes()->where('member_id', $member->id)->exists(),
            ])
            ->sortByDesc('open')
            ->values();

        return Inertia::render('votes/index', ['polls' => $polls, 'canManage' => $manager]);
    }

    public function show(Request $request, Poll $poll): Response
    {
        $member = $request->user()->member;
        $manager = $request->user()->hasPermission(Permission::MeetingsManage->value);
        $mine = $member ? $poll->votes()->where('member_id', $member->id)->first() : null;
        $showResults = ! $poll->isOpen() || $mine !== null || $manager;

        return Inertia::render('votes/show', [
            'poll' => [
                'id' => $poll->id,
                'question' => $poll->question,
                'details' => $poll->details,
                'options' => $poll->options,
                'closes_at' => $poll->closes_at->toIso8601String(),
                'closed_at' => $poll->closed_at?->toIso8601String(),
                'open' => $poll->isOpen(),
                'created_by' => $poll->creator?->name,
                'created_at' => $poll->created_at?->toIso8601String(),
            ],
            'myChoice' => $mine?->choice,
            'canVote' => $poll->isOpen() && $member?->isActive() === true,
            'results' => $showResults ? $poll->tally() : null,
            'turnout' => ['votes' => $poll->votes()->count(), 'eligible' => Member::query()->active()->count()],
            // Who voted, never what they chose.
            'voters' => $manager ? $poll->votes()->with('member')->get()->map(fn (PollVote $v) => $v->member->name)->sort()->values() : null,
            'canManage' => $manager,
        ]);
    }

    public function store(Request $request, Notifier $notifier): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:200'],
            'details' => ['nullable', 'string', 'max:2000'],
            'options' => ['nullable', 'array', 'max:6'],
            'options.*' => ['nullable', 'string', 'max:80'],
            'closes_on' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays(60)->toDateString()],
            'notify' => ['boolean'],
        ]);

        $options = array_values(array_unique(array_filter(array_map('trim', $data['options'] ?? []), fn ($o) => $o !== '')));
        $options = $options === [] ? ['Yes', 'No'] : $options;

        if (count($options) < 2) {
            throw new RuleViolation('Give at least two answers to choose from, or leave them blank for Yes and No.');
        }

        $poll = Poll::create([
            'question' => $data['question'],
            'details' => $data['details'] ?? null,
            'options' => $options,
            'closes_at' => CarbonImmutable::parse($data['closes_on'])->endOfDay(),
            'created_by' => $request->user()->id,
        ]);

        Audit::log('poll.created', "Opened a vote: {$poll->question}", $poll);

        if ($data['notify'] ?? false) {
            Member::query()->active()->get()->each(fn (Member $m) => $notifier->toMember($m, 'poll_opened', [
                'question' => $poll->question,
                'closes' => $poll->closes_at->format('j M'),
                'link' => url("/votes/{$poll->id}"),
            ]));
        }

        $this->toast('Vote opened.');

        return to_route('votes.show', $poll);
    }

    public function vote(Request $request, Poll $poll): RedirectResponse
    {
        $member = $request->user()->member;

        if (! $poll->isOpen()) {
            throw new RuleViolation('This vote has closed.');
        }

        if ($member === null || ! $member->isActive()) {
            throw new RuleViolation('Only active members can vote.');
        }

        $data = $request->validate(['choice' => ['required', 'integer', 'min:0', 'max:'.(count($poll->options) - 1)]]);

        PollVote::updateOrCreate(['poll_id' => $poll->id, 'member_id' => $member->id], ['choice' => $data['choice']]);
        $this->toast('Your vote is in. You can change it until the vote closes.');

        return back();
    }

    public function close(Poll $poll): RedirectResponse
    {
        if ($poll->closed_at !== null) {
            throw new RuleViolation('This vote is already closed.');
        }

        $poll->update(['closed_at' => now()]);
        Audit::log('poll.closed', "Closed the vote: {$poll->question}", $poll, ['results' => $poll->tally()]);
        $this->toast('Vote closed.');

        return back();
    }
}
