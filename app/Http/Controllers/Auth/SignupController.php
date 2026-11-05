<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Access\Permission;
use App\Domain\Audit\Audit;
use App\Domain\Members\Actions\ApproveSignup;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\SignupRequest;
use App\Domain\Notifications\Notifier;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Phone;
use App\Support\RuleViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-service sign-up. A request waits for someone who manages members;
 * the login only exists once they approve it.
 */
class SignupController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request, Notifier $notifier): RedirectResponse
    {
        $group = $this->group();

        $request->merge([
            'phone' => Phone::normalize((string) $request->input('phone')) ?? $request->input('phone'),
            'email' => $request->filled('email') ? Str::lower(trim((string) $request->input('email'))) : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^254[17]\d{8}$/'],
            'email' => ['nullable', 'email', 'max:120'],
            'id_number' => ['required', 'string', 'max:20'],
            'locale' => ['nullable', Rule::in(['en', 'sw'])],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'phone.regex' => 'Enter a valid Kenyan mobile number, e.g. 0712 345 678.',
        ]);

        $hasLogin = User::query()->where('phone', $data['phone'])->exists()
            || Member::query()->where('phone', $data['phone'])->whereNotNull('user_id')->exists();

        if ($hasLogin) {
            throw ValidationException::withMessages(['phone' => 'This number already has a login. Sign in, or use "Forgot your password".']);
        }

        if ($data['email'] !== null && User::query()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages(['email' => 'This email already has a login. Sign in, or use "Forgot your password".']);
        }

        if (SignupRequest::query()->where('phone', $data['phone'])->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['phone' => 'A request for this number is already waiting for approval.']);
        }

        $idNumber = trim($data['id_number']);
        $match = Member::query()->whereNull('user_id')->where('phone', $data['phone'])->first()
            ?? Member::query()->whereNull('user_id')->where('id_number', $idNumber)->first();

        $signup = SignupRequest::create([
            'group_id' => $group->id,
            'member_id' => $match?->id,
            'name' => trim($data['name']),
            'phone' => $data['phone'],
            'email' => $data['email'],
            'id_number' => $idNumber,
            'locale' => $data['locale'] ?? 'en',
            'password' => $data['password'],
        ]);

        Audit::log('member.signup_requested', "{$signup->name} asked for a login", $signup, [], $group->id);
        $notifier->toHoldersOf($group, Permission::MembersManage->value, 'signup_new', [
            'applicant' => $signup->name,
            'phone' => Phone::display($signup->phone),
            'kind' => $match ? "an existing member ({$match->member_number})" : 'a new member',
            'kind_sw' => $match ? "mwanachama aliyepo ({$match->member_number})" : 'mwanachama mpya',
            'link' => url('/members'),
        ]);

        return to_route('login')->with('status', 'Request sent. You will get an SMS once the secretary approves it.');
    }

    public function decide(Request $request, SignupRequest $signup, ApproveSignup $approve, Notifier $notifier): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        if ($signup->status !== 'pending') {
            throw new RuleViolation('This request has already been answered.');
        }

        if ($data['decision'] === 'approved') {
            $member = $approve->handle($signup, $request->user());
            $this->toast("{$member->name} can now sign in as {$member->member_number}.");

            return back();
        }

        $signup->update([
            'status' => 'rejected',
            'reason' => $data['reason'] ?? null,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);

        Audit::log('member.signup_rejected', "Rejected sign-up from {$signup->name}", $signup);

        $group = $this->group();
        $template = $notifier->template($group, 'signup_rejected', 'sms', $signup->locale);

        if ($template !== null) {
            $notifier->toNumber($group, $signup->phone, $notifier->render($template['body'], [
                'name' => Str::before($signup->name, ' '),
                'group' => $group->name,
                'reason' => $data['reason'] ?? '',
            ]));
        }

        $this->toast('Sign-up rejected.');

        return back();
    }
}
