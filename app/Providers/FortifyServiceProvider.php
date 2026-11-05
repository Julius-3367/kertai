<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Domain\Members\Models\SignupRequest;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkeys;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Members sign in with either their email or their phone number.
        Fortify::authenticateUsing(function (Request $request): ?User {
            $login = trim((string) $request->input(Fortify::username()));
            $phone = Phone::normalize($login);

            $user = User::query()
                ->when(
                    $phone !== null,
                    fn ($q) => $q->where('phone', $phone),
                    fn ($q) => $q->where('email', Str::lower($login)),
                )
                ->first();

            if (! $user) {
                $this->refuseWaitingSignup($request, $login, $phone);

                return null;
            }

            if (! Hash::check((string) $request->input('password'), $user->password) || ! $user->canSignIn()) {
                return null;
            }

            $user->forceFill(['last_login_at' => now()])->saveQuietly();

            return $user;
        });

        // Passkey sign-in skips authenticateUsing, so apply the same rule here.
        Passkeys::authorizeLoginUsing(fn (Request $request, PasskeyUser $user) => $user instanceof User && $user->canSignIn());
    }

    /**
     * Someone who signed up but is not approved yet has no user, so a plain
     * "these credentials do not match" would confuse them. Only say more when
     * the password is right, so the form does not reveal who has applied.
     */
    private function refuseWaitingSignup(Request $request, string $login, ?string $phone): void
    {
        $signup = SignupRequest::withoutGlobalScopes()
            ->when(
                $phone !== null,
                fn ($q) => $q->where('phone', $phone),
                fn ($q) => $q->where('email', Str::lower($login)),
            )
            ->whereIn('status', ['pending', 'rejected'])
            ->latest('id')
            ->first();

        if (! $signup || ! Hash::check((string) $request->input('password'), $signup->password)) {
            return;
        }

        throw ValidationException::withMessages([
            Fortify::username() => $signup->status === 'pending'
                ? 'Your account is waiting for approval. You will get an SMS once the secretary approves it.'
                : 'Your sign-up was not approved. Please contact the secretary.',
        ]);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
