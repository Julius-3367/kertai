<?php

namespace App\Domain\Notifications;

use App\Domain\Groups\Models\Group;
use App\Domain\Members\MemberFinance;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Jobs\SendOutboundMessage;
use App\Domain\Notifications\Models\InboxItem;
use App\Domain\Notifications\Models\MessageTemplate;
use App\Domain\Notifications\Models\OutboundMessage;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Support\Collection;

final class Notifier
{
    public function __construct(private readonly MemberFinance $finance) {}

    /**
     * Send a templated message to a member on every channel they have
     * switched on. Reminder templates respect the member's opt-out.
     *
     * @param  array<string, string|int|null>  $data
     * @param  list<string>|null  $channels
     * @param  array<string, mixed>  $meta  e.g. ['meeting_id' => 5] to attach its calendar file to emails
     * @return list<OutboundMessage>
     */
    public function toMember(Member $member, string $key, array $data = [], ?array $channels = null, array $meta = []): array
    {
        if (in_array($key, DefaultTemplates::REMINDERS, true) && $member->reminders_opt_out) {
            return [];
        }

        $group = Group::find($member->group_id);
        // The caller's values win, so a specific link (a loan, a vote)
        // replaces the plain site address.
        $data = [...$this->baseData($group, $member), ...$data];
        $this->toInbox($group, $member, $key, $data);
        $sent = [];

        foreach ($channels ?? ['sms', 'email'] as $channel) {
            $recipient = $this->recipient($member, $channel);

            if ($recipient === null) {
                continue;
            }

            $template = $this->template($group, $key, $channel, $member->locale);

            if ($template === null) {
                continue;
            }

            $sent[] = $this->queue(
                $group,
                $channel,
                $recipient,
                $this->render($template['subject'], $data),
                $this->render($template['body'], $data),
                $member,
                $key,
                meta: $meta,
            );
        }

        return $sent;
    }

    /**
     * Notify every member currently holding a permission (e.g. approvers).
     *
     * @param  array<string, string|int|null>  $data
     */
    public function toHoldersOf(Group $group, string $permission, string $key, array $data = [], ?int $exceptUserId = null): int
    {
        $count = 0;

        $this->holdersOf($group, $permission)
            ->reject(fn (Member $m) => $exceptUserId !== null && $m->user_id === $exceptUserId)
            ->each(function (Member $member) use ($key, $data, &$count) {
                $count += count($this->toMember($member, $key, $data));
            });

        return $count;
    }

    /**
     * Free-text message to a member; {name}, {savings}, {arrears} and the
     * other placeholders are filled per member.
     */
    public function compose(Member $member, string $channel, string $body, ?string $subject = null, ?int $createdBy = null): ?OutboundMessage
    {
        $recipient = $this->recipient($member, $channel, ignorePreference: true);

        if ($recipient === null) {
            return null;
        }

        $group = Group::find($member->group_id);
        $data = $this->baseData($group, $member, withBalances: true);

        // A broadcast usually goes by SMS and email; file it once.
        if ($member->user_id !== null && ($channel === 'sms' || $this->recipient($member, 'sms', ignorePreference: true) === null)) {
            InboxItem::withoutGlobalScopes()->create([
                'group_id' => $group->id,
                'user_id' => $member->user_id,
                'title' => $subject ? $this->render($subject, $data) : "Message from {$group->name}",
                'body' => $this->render($body, $data),
            ]);
        }

        return $this->queue(
            $group,
            $channel,
            $recipient,
            $subject ? $this->render($subject, $data) : $group->name,
            $this->render($body, $data),
            $member,
            null,
            $createdBy,
        );
    }

    /**
     * Message to someone who is not a member (fundraiser donor, hirer).
     */
    public function toNumber(Group $group, string $phone, string $body): ?OutboundMessage
    {
        $normalized = Phone::normalize($phone);

        return $normalized ? $this->queue($group, 'sms', $normalized, null, $body, null, null) : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function queue(
        Group $group,
        string $channel,
        string $recipient,
        ?string $subject,
        string $body,
        ?Member $member,
        ?string $templateKey,
        ?int $createdBy = null,
        array $meta = [],
    ): OutboundMessage {
        $message = OutboundMessage::withoutGlobalScopes()->create([
            'group_id' => $group->id,
            'member_id' => $member?->id,
            'channel' => $channel,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'template_key' => $templateKey,
            'meta' => $meta ?: null,
            'status' => 'queued',
            'created_by' => $createdBy ?? auth()->id(),
        ]);

        SendOutboundMessage::dispatch($message->id)->afterCommit();

        return $message;
    }

    /**
     * @param  array<string, string|int|null>  $data
     */
    public function render(?string $text, array $data): string
    {
        if ($text === null) {
            return '';
        }

        $replace = [];
        foreach ($data as $key => $value) {
            $replace['{'.$key.'}'] = (string) $value;
        }

        return trim(strtr($text, $replace));
    }

    /**
     * @return array{subject: string, body: string}|null
     */
    public function template(Group $group, string $key, string $channel, string $locale = 'en'): ?array
    {
        $stored = MessageTemplate::withoutGlobalScopes()
            ->where('group_id', $group->id)
            ->where('key', $key)
            ->where('channel', $channel)
            ->whereIn('locale', array_unique([$locale, 'en']))
            ->get()
            ->sortBy(fn (MessageTemplate $t) => $t->locale === $locale ? 0 : 1)
            ->first();

        if ($stored) {
            return ['subject' => (string) $stored->subject, 'body' => $stored->body];
        }

        $default = DefaultTemplates::all()[$key] ?? null;

        if ($default === null) {
            return null;
        }

        return ['subject' => $default['subject'], 'body' => $default[$locale] ?? $default['en']];
    }

    /** @return Collection<int, Member> */
    public function holdersOf(Group $group, string $permission): Collection
    {
        return Member::withoutGlobalScopes()
            ->where('group_id', $group->id)
            ->active()
            ->whereHas('terms', fn ($q) => $q->current())
            ->get()
            ->filter(fn (Member $m) => $m->hasPermission($permission))
            ->values();
    }

    /**
     * @return array<string, string|int|null>
     */
    private function baseData(Group $group, Member $member, bool $withBalances = true): array
    {
        $data = [
            'name' => $member->firstName(),
            'full_name' => $member->name,
            'member_number' => $member->member_number,
            'group' => $group->name,
            'paybill' => $group->paybill ?? '',
            'account' => $group->paybill_account ?? '',
            'link' => rtrim((string) config('app.url'), '/'),
        ];

        if ($withBalances) {
            $summary = $this->finance->summary($member);
            $data['savings'] = Money::format($summary['savings']);
            $data['arrears'] = Money::format($summary['arrears']);
            $data['fines'] = Money::format($summary['fines']);
            $data['outstanding'] = Money::format($summary['outstanding']);
        }

        return $data;
    }

    /**
     * Files the message under the member's bell, whatever their SMS and
     * email choices, so nothing is missed and it costs nothing.
     *
     * @param  array<string, string|int|null>  $data
     */
    private function toInbox(Group $group, Member $member, string $key, array $data): void
    {
        // Login details carry a password; they stay in SMS and email only.
        if ($member->user_id === null || $key === 'account_created') {
            return;
        }

        $template = $this->template($group, $key, 'sms', $member->locale) ?? $this->template($group, $key, 'email', $member->locale);

        if ($template === null) {
            return;
        }

        $base = rtrim((string) config('app.url'), '/');
        $link = (string) ($data['link'] ?? '');
        $url = $link !== '' && $link !== $base
            ? (str_starts_with($link, $base) ? substr($link, strlen($base)) : $link)
            : $this->inboxLink($key, $member);

        InboxItem::withoutGlobalScopes()->create([
            'group_id' => $group->id,
            'user_id' => $member->user_id,
            'title' => $this->render($template['subject'], $data) ?: (DefaultTemplates::all()[$key]['label'] ?? $group->name),
            'body' => $this->render($template['body'], $data),
            'url' => $url,
        ]);
    }

    private function inboxLink(string $key, Member $member): ?string
    {
        return match (true) {
            in_array($key, ['payment_received', 'fine_applied', 'contribution_reminder', 'arrears_reminder'], true) => "/statements/{$member->id}",
            str_starts_with($key, 'loan_') || str_starts_with($key, 'guarantor_') => '/me/loans',
            str_starts_with($key, 'meeting_') => '/meetings',
            str_starts_with($key, 'mgr_') => '/mgr',
            str_starts_with($key, 'change_request_') => "/members/{$member->id}",
            default => null,
        };
    }

    private function recipient(Member $member, string $channel, bool $ignorePreference = false): ?string
    {
        if ($channel === 'sms') {
            return ($ignorePreference || $member->notify_sms) ? Phone::normalize($member->phone) : null;
        }

        return ($ignorePreference || $member->notify_email) && $member->email ? $member->email : null;
    }
}
