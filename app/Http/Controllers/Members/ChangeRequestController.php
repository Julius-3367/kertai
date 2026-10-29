<?php

namespace App\Http\Controllers\Members;

use App\Domain\Access\Permission;
use App\Domain\Audit\Audit;
use App\Domain\Members\Actions\SaveMember;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberChangeRequest;
use App\Domain\Notifications\Notifier;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Phone;
use App\Support\RuleViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Members ask to change their own phone, email, date of birth or nominee;
 * someone who manages members approves before the register changes.
 */
class ChangeRequestController extends Controller
{
    public function store(Request $request, Notifier $notifier): RedirectResponse
    {
        $member = $request->user()->member ?? abort(403);
        $groupId = $this->group()->id;

        foreach (['phone', 'nominee_phone'] as $key) {
            if ($request->filled($key)) {
                $request->merge([$key => Phone::normalize($request->input($key)) ?? $request->input($key)]);
            }
        }

        $data = $request->validate([
            'phone' => ['nullable', 'regex:/^254[17]\d{8}$/', Rule::unique('members', 'phone')->where('group_id', $groupId)->ignore($member->id)],
            'email' => ['nullable', 'email', 'max:120'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nominee_name' => ['nullable', 'string', 'max:120'],
            'nominee_phone' => ['nullable', 'regex:/^254[17]\d{8}$/'],
            'nominee_relationship' => ['nullable', 'string', 'max:40'],
            'nominee_id_number' => ['nullable', 'string', 'max:20'],
        ], [
            'phone.regex' => 'Enter a valid Kenyan mobile number, e.g. 0712 345 678.',
            'phone.unique' => 'Another member already uses this number.',
            'nominee_phone.regex' => 'Enter a valid Kenyan mobile number, e.g. 0712 345 678.',
        ]);

        $current = $this->current($member);
        $changes = [];

        foreach (MemberChangeRequest::FIELDS as $field => $label) {
            // Fields left out are unchanged; a field sent empty is cleared.
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $new = isset($data[$field]) && $data[$field] !== '' ? (string) $data[$field] : null;
            $new = $field === 'email' && $new !== null ? strtolower($new) : $new;
            $new = $field === 'date_of_birth' && $new !== null ? date('Y-m-d', (int) strtotime($new)) : $new;

            // A blank phone means "keep it"; the number can only be changed.
            if ($field === 'phone' && $new === null) {
                continue;
            }

            if ($new !== $current[$field]) {
                $changes[$field] = $new;
            }
        }

        if ($changes === []) {
            throw new RuleViolation('Nothing has changed.');
        }

        $change = DB::transaction(function () use ($member, $changes, $current) {
            MemberChangeRequest::query()->where('member_id', $member->id)->where('status', 'pending')->update(['status' => 'withdrawn']);

            return MemberChangeRequest::create([
                'member_id' => $member->id,
                'changes' => $changes,
                'previous' => array_intersect_key($current, $changes),
            ]);
        });

        Audit::log('member.change_requested', "{$member->name} asked to change ".$this->labels($change), $member);
        $notifier->toHoldersOf($this->group(), Permission::MembersManage->value, 'change_request_new', [
            'member' => $member->name,
            'fields' => $this->labels($change),
            'link' => url("/members/{$member->id}"),
        ], exceptUserId: $member->user_id);

        $this->toast('Sent for approval. Your details change once the secretary approves.');

        return back();
    }

    public function withdraw(Request $request, MemberChangeRequest $change): RedirectResponse
    {
        abort_unless($change->member_id === $request->user()->member?->id, 403);

        if ($change->status !== 'pending') {
            throw new RuleViolation('This request has already been answered.');
        }

        $change->update(['status' => 'withdrawn']);
        $this->toast('Request withdrawn.');

        return back();
    }

    public function decide(Request $request, MemberChangeRequest $change, SaveMember $save, Notifier $notifier): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        if ($change->status !== 'pending') {
            throw new RuleViolation('This request has already been answered.');
        }

        $member = $change->member;

        if ($change->member_id === $request->user()->member?->id) {
            throw new RuleViolation('Someone else must approve changes to your own details.');
        }

        if ($data['decision'] === 'approved') {
            $phone = $change->changes['phone'] ?? null;

            if ($phone !== null && (
                Member::query()->where('phone', $phone)->where('id', '!=', $member->id)->exists()
                || User::query()->where('phone', $phone)->where('id', '!=', $member->user_id)->exists()
            )) {
                throw new RuleViolation('Another member or login already uses '.Phone::display($phone).'.');
            }

            // SaveMember clears phone, email and nominee phone when they are
            // missing, so pass the current values alongside the changes.
            $save->handle($this->group(), [
                'phone' => $member->phone,
                'email' => $member->email,
                'nominee_phone' => $member->nominee_phone,
                ...$change->changes,
            ], $member);
        }

        $change->update([
            'status' => $data['decision'],
            'reason' => $data['reason'] ?? null,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        Audit::log("member.change_{$data['decision']}", ucfirst($data['decision'])." {$member->name}'s change to ".$this->labels($change), $member);
        $notifier->toMember($member->refresh(), 'change_request_decided', [
            'fields' => $this->labels($change),
            'decision' => $data['decision'],
            'decision_sw' => $data['decision'] === 'approved' ? 'kubaliwa' : 'kataliwa',
            'reason' => $data['reason'] ?? '',
            'link' => url("/members/{$member->id}"),
        ]);

        $this->toast($data['decision'] === 'approved' ? 'Change approved and saved.' : 'Change rejected.');

        return back();
    }

    /**
     * @return array<string, string|null>
     */
    private function current(Member $member): array
    {
        return [
            'phone' => $member->phone,
            'email' => $member->email,
            'date_of_birth' => $member->date_of_birth?->toDateString(),
            'nominee_name' => $member->nominee_name,
            'nominee_phone' => $member->nominee_phone ? Phone::normalize($member->nominee_phone) ?? $member->nominee_phone : null,
            'nominee_relationship' => $member->nominee_relationship,
            'nominee_id_number' => $member->nominee_id_number,
        ];
    }

    private function labels(MemberChangeRequest $change): string
    {
        return implode(', ', array_map(fn ($f) => strtolower(MemberChangeRequest::FIELDS[$f] ?? $f), array_keys($change->changes)));
    }
}
