<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * First sign-in with a password an official issued: choose your own.
 */
class FirstPasswordController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/first-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => $this->passwordRules(),
        ]);

        $request->user()->forceFill([
            'password' => $request->string('password')->toString(),
            'must_change_password' => false,
        ])->save();

        $this->toast('Password changed. Welcome!');

        return to_route('dashboard');
    }
}
