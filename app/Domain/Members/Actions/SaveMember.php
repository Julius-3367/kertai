<?php

namespace App\Domain\Members\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Groups\Models\Group;
use App\Domain\Members\Models\Member;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;

final class SaveMember
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Group $group, array $data, ?Member $member = null): Member
    {
        $data['phone'] = Phone::normalize($data['phone'] ?? null) ?? $data['phone'];
        $data['nominee_phone'] = isset($data['nominee_phone']) ? (Phone::normalize($data['nominee_phone']) ?? $data['nominee_phone']) : null;
        $data['email'] = isset($data['email']) && $data['email'] !== '' ? strtolower($data['email']) : null;

        return DB::transaction(function () use ($group, $data, $member) {
            if ($member === null) {
                $data['member_number'] ??= $this->nextNumber($group);
                $member = new Member(['group_id' => $group->id]);
                $member->group_id = $group->id;
            }

            $member->fill($data)->save();

            // Keep the login in step with the register.
            if ($member->user) {
                $member->user->forceFill([
                    'name' => $member->name,
                    'phone' => Phone::normalize($member->phone),
                    'email' => $member->email,
                    'locale' => $member->locale,
                ])->save();
            }

            Audit::log($member->wasRecentlyCreated ? 'member.created' : 'member.updated', ($member->wasRecentlyCreated ? 'Added' : 'Updated')." member {$member->member_number} {$member->name}", $member, array_keys($member->getChanges()), $group->id);

            return $member;
        });
    }

    public function nextNumber(Group $group): string
    {
        $numbers = Member::withoutGlobalScopes()->where('group_id', $group->id)->pluck('member_number');
        $max = $numbers->map(fn ($n) => (int) preg_replace('/\D/', '', (string) $n))->max() ?? 0;

        return 'M'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }
}
