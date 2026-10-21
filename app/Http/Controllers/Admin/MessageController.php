<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Audit;
use App\Domain\Dues\Models\Due;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\DefaultTemplates;
use App\Domain\Notifications\Jobs\SendOutboundMessage;
use App\Domain\Notifications\Models\MessageTemplate;
use App\Domain\Notifications\Models\OutboundMessage;
use App\Domain\Notifications\Notifier;
use App\Http\Controllers\Controller;
use App\Support\RuleViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MessageController extends Controller
{
    public function index(Request $request): Response
    {
        $messages = OutboundMessage::query()
            ->with('member:id,name')
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('channel')->toString(), fn ($q, $c) => $q->where('channel', $c))
            ->latest('id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (OutboundMessage $m) => [
                'id' => $m->id,
                'member' => $m->member?->name,
                'channel' => $m->channel,
                'recipient' => $m->recipient,
                'subject' => $m->subject,
                'body' => $m->body,
                'template_key' => $m->template_key,
                'status' => $m->status,
                'provider' => $m->provider,
                'attempts' => $m->attempts,
                'error' => $m->error,
                'created_at' => $m->created_at->toIso8601String(),
                'sent_at' => $m->sent_at?->toIso8601String(),
            ]);

        return Inertia::render('messages/index', [
            'messages' => $messages,
            'filters' => $request->only('status', 'channel'),
            'members' => $this->memberOptions(),
            'counts' => [
                'queued' => OutboundMessage::query()->where('status', 'queued')->count(),
                'failed' => OutboundMessage::query()->where('status', 'failed')->count(),
                'sent_today' => OutboundMessage::query()->where('status', 'sent')->where('sent_at', '>=', today())->count(),
            ],
        ]);
    }

    public function send(Request $request, Notifier $notifier): RedirectResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(['all', 'arrears', 'officials', 'selected'])],
            'member_ids' => ['required_if:audience,selected', 'array'],
            'member_ids.*' => ['integer'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(['sms', 'email'])],
            'subject' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:918'],
        ]);

        $members = $this->audience($data['audience'], $data['member_ids'] ?? []);

        if ($members->isEmpty()) {
            throw new RuleViolation('Nobody matches that audience.');
        }

        $queued = 0;
        foreach ($members as $member) {
            foreach ($data['channels'] as $channel) {
                $queued += $notifier->compose($member, $channel, $data['body'], $data['subject'] ?? null, $request->user()->id) ? 1 : 0;
            }
        }

        Audit::log('messages.sent', "Sent a message to {$members->count()} members ({$queued} messages)", null, ['audience' => $data['audience']]);
        $this->toast("{$queued} messages queued to {$members->count()} members.");

        return back();
    }

    public function retry(OutboundMessage $message): RedirectResponse
    {
        if ($message->status !== 'failed') {
            throw new RuleViolation('Only failed messages can be retried.');
        }

        $message->update(['status' => 'queued', 'attempts' => 0, 'error' => null]);
        SendOutboundMessage::dispatch($message->id);
        $this->toast('Message queued again.');

        return back();
    }

    public function retryFailed(): RedirectResponse
    {
        $failed = OutboundMessage::query()->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->get();

        foreach ($failed as $message) {
            $message->update(['status' => 'queued', 'attempts' => 0, 'error' => null]);
            SendOutboundMessage::dispatch($message->id);
        }

        $this->toast("{$failed->count()} failed messages queued again.");

        return back();
    }

    public function templates(Notifier $notifier): Response
    {
        $group = $this->group();
        $rows = [];

        foreach (DefaultTemplates::all() as $key => $default) {
            foreach (['sms', 'email'] as $channel) {
                foreach (['en', 'sw'] as $locale) {
                    $stored = MessageTemplate::query()->where(['key' => $key, 'channel' => $channel, 'locale' => $locale])->first();
                    $current = $notifier->template($group, $key, $channel, $locale);

                    $rows[] = [
                        'key' => $key,
                        'label' => $default['label'],
                        'channel' => $channel,
                        'locale' => $locale,
                        'subject' => $current['subject'] ?? '',
                        'body' => $current['body'] ?? '',
                        'customised' => $stored !== null,
                        'reminder' => in_array($key, DefaultTemplates::REMINDERS, true),
                    ];
                }
            }
        }

        return Inertia::render('messages/templates', ['templates' => $rows]);
    }

    public function updateTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', Rule::in(array_keys(DefaultTemplates::all()))],
            'channel' => ['required', Rule::in(['sms', 'email'])],
            'locale' => ['required', Rule::in(['en', 'sw'])],
            'subject' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'reset' => ['boolean'],
        ]);

        $where = ['key' => $data['key'], 'channel' => $data['channel'], 'locale' => $data['locale']];

        if ($data['reset'] ?? false) {
            MessageTemplate::query()->where($where)->delete();
            $this->toast('Template reset to the standard wording.');

            return back();
        }

        MessageTemplate::query()->updateOrCreate($where, ['subject' => $data['subject'] ?? null, 'body' => $data['body']]);
        Audit::log('template.updated', "Edited {$data['channel']} template {$data['key']} ({$data['locale']})");
        $this->toast('Template saved.');

        return back();
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Member>
     */
    private function audience(string $audience, array $ids): Collection
    {
        $active = Member::query()->active();

        return match ($audience) {
            'arrears' => $active->whereIn('id', Due::query()->overdue()->select('member_id'))->get(),
            'officials' => $active->whereHas('terms', fn ($q) => $q->current()->whereHas('position', fn ($p) => $p->where('is_official', true)))->get(),
            'selected' => $active->whereIn('id', $ids)->get(),
            default => $active->get(),
        };
    }
}
