<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accounts belong to the member register, so members edit their name and
 * email here but cannot delete their login; the secretary handles exits.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $member = $request->user()->member;

        return Inertia::render('settings/profile', [
            'status' => $request->session()->get('status'),
            'preferences' => $member ? [
                'notify_sms' => $member->notify_sms,
                'notify_email' => $member->notify_email,
                'reminders_opt_out' => $member->reminders_opt_out,
                'show_name_publicly' => $member->show_name_publicly,
                'locale' => $member->locale,
            ] : null,
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = $user->email ? now() : null;
        }

        $user->save();

        if ($user->member) {
            $user->member->forceFill(['name' => $user->name, 'email' => $user->email])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }
}
