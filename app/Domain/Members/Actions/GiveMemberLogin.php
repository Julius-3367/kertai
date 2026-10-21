<?php

namespace App\Domain\Members\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Notifier;
use App\Models\User;
use App\Support\Phone;
use App\Support\RuleViolation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates (or resets) a member's login with a one-time password and sends
 * it by SMS and email. The member must change it on first sign-in.
 */
final class GiveMemberLogin
{
    public function __construct(private readonly Notifier $notifier) {}

    public function handle(Member $member, bool $notify = true): string
    {
        $phone = Phone::normalize($member->phone);

        if ($phone === null && ! $member->email) {
            throw new RuleViolation('The member needs a valid phone number or email to sign in.');
        }

        $password = $this->temporaryPassword();

        DB::transaction(function () use ($member, $phone, $password) {
            $clash = User::query()
                ->where(fn ($q) => $q->when($phone, fn ($q) => $q->where('phone', $phone))
                    ->when($member->email, fn ($q) => $q->orWhere('email', $member->email)))
                ->when($member->user_id, fn ($q) => $q->where('id', '!=', $member->user_id))
                ->exists();

            if ($clash) {
                throw new RuleViolation('Another login already uses this phone number or email.');
            }

            $user = $member->user ?? new User;
            $user->forceFill([
                'group_id' => $member->group_id,
                'name' => $member->name,
                'phone' => $phone,
                'email' => $member->email,
                'locale' => $member->locale,
                'password' => $password,
                'must_change_password' => true,
                'email_verified_at' => $member->email ? now() : null,
            ])->save();

            if ($member->user_id !== $user->id) {
                $member->forceFill(['user_id' => $user->id])->save();
            }
        });

        Audit::log('member.login_issued', "Login details issued to {$member->name}", $member);

        if ($notify) {
            $this->notifier->toMember($member->refresh(), 'account_created', [
                'login' => $phone ? Phone::display($phone) : $member->email,
                'password' => $password,
                'link' => url('/login'),
            ], ['sms', 'email']);
        }

        return $password;
    }

    private function temporaryPassword(): string
    {
        // Easy to type on a phone, still about 41 bits of entropy.
        $words = ['kahawa', 'shamba', 'mvua', 'jua', 'nyota', 'mlima', 'ziwa', 'msitu', 'chai', 'miwa', 'ndege', 'simba'];

        return ucfirst($words[random_int(0, count($words) - 1)]).'-'.random_int(1000, 9999).'-'.Str::upper(Str::random(4));
    }
}
