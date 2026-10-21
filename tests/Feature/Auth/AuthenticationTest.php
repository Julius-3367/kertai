<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_with_their_email()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'login' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_members_can_authenticate_with_their_phone_in_any_format()
    {
        $member = $this->member(['phone' => '0712 345 678', 'email' => null]);

        foreach (['0712345678', '+254 712 345 678', '254712345678'] as $phone) {
            $this->post(route('login.store'), ['login' => $phone, 'password' => 'password']);
            $this->assertAuthenticatedAs($member->user);
            $this->post(route('logout'));
        }
    }

    public function test_members_who_have_left_cannot_sign_in()
    {
        $member = $this->member(['status' => 'exited']);

        $this->post(route('login.store'), ['login' => $member->user->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'login' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'login' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'login' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
