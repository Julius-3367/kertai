<?php

namespace App\Domain\Members\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Groups\Models\Group;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\SignupRequest;
use App\Domain\Notifications\Notifier;
use App\Models\User;
use App\Support\Phone;
use App\Support\RuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * Turns an approved sign-up into a login: links the member it matched, or
 * adds a new member, then creates the user with the password they chose.
 */
final class ApproveSignup
{
    public function __construct(
        private readonly SaveMember $save,
        private readonly Notifier $notifier,
    ) {}

    public function handle(SignupRequest $signup, User $by): Member
    {
        $member = DB::transaction(function () use ($signup, $by) {
            $signup = SignupRequest::query()->lockForUpdate()->findOrFail($signup->id);

            if ($signup->status !== 'pending') {
                throw new RuleViolation('This request has already been answered.');
            }

            $group = Group::query()->findOrFail($signup->group_id);
            $member = $signup->member_id ? Member::query()->find($signup->member_id) : null;

            if ($member !== null) {
                if ($member->user_id !== null) {
                    throw new RuleViolation("{$member->name} already has a login.");
                }

                if (! $member->isActive()) {
                    throw new RuleViolation("{$member->member_number} {$member->name} is {$member->status}, so a login would not work.");
                }

                // Matched on ID number with a different phone: the register
                // takes the number they signed up with, as they will sign in with it.
                if (Phone::normalize($member->phone) !== $signup->phone) {
                    $this->assertPhoneFree($signup->phone, $member->id);
                    $member = $this->save->handle($group, [
                        'phone' => $signup->phone,
                        'email' => $member->email ?? $signup->email,
                        'nominee_phone' => $member->nominee_phone,
                    ], $member);
                }
            } else {
                $this->assertPhoneFree($signup->phone);
                $member = $this->save->handle($group, [
                    'name' => $signup->name,
                    'phone' => $signup->phone,
                    'email' => $signup->email,
                    'id_number' => $signup->id_number,
                    'locale' => $signup->locale,
                    'joined_on' => now()->toDateString(),
                ])->refresh();
            }

            $phone = Phone::normalize($member->phone);
            $clash = User::query()
                ->where(fn ($q) => $q->where('phone', $phone)
                    ->when($member->email, fn ($q) => $q->orWhere('email', $member->email)))
                ->exists();

            if ($clash) {
                throw new RuleViolation('Another login already uses this phone number or email.');
            }

            // The stored value is already a hash; the hashed cast keeps it as is.
            $user = new User;
            $user->forceFill([
                'group_id' => $group->id,
                'name' => $member->name,
                'phone' => $phone,
                'email' => $member->email,
                'locale' => $member->locale,
                'password' => $signup->password,
                'must_change_password' => false,
            ])->save();

            $member->forceFill(['user_id' => $user->id])->save();

            $signup->update([
                'status' => 'approved',
                'member_id' => $member->id,
                'decided_by' => $by->id,
                'decided_at' => now(),
            ]);

            Audit::log('member.signup_approved', "Approved sign-up for {$member->member_number} {$member->name}", $member);

            return $member;
        });

        $this->notifier->toMember($member->refresh(), 'signup_approved', [
            'login' => Phone::display($member->phone),
            'link' => url('/login'),
        ], ['sms', 'email']);

        return $member;
    }

    private function assertPhoneFree(string $phone, ?int $exceptMemberId = null): void
    {
        $taken = Member::query()
            ->where('phone', $phone)
            ->when($exceptMemberId, fn ($q) => $q->where('id', '!=', $exceptMemberId))
            ->exists();

        if ($taken) {
            throw new RuleViolation('Another member already uses '.Phone::display($phone).'.');
        }
    }
}
