<?php

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = $this->member()->user;

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('settings/profile')->has('preferences'));
    }

    public function test_profile_information_can_be_updated_and_the_register_follows()
    {
        $member = $this->member();

        $this->actingAs($member->user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('Test User', $member->user->refresh()->name);
        $this->assertSame('test@example.com', $member->user->email);
        $this->assertSame('Test User', $member->refresh()->name);
        $this->assertSame('test@example.com', $member->email);
    }

    public function test_email_is_optional()
    {
        $member = $this->member();

        $this->actingAs($member->user)
            ->patch(route('profile.update'), ['name' => 'Phone Only', 'email' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($member->user->refresh()->email);
    }

    public function test_members_cannot_delete_their_own_login()
    {
        $user = $this->member()->user;

        $this->actingAs($user)->delete('/settings/profile', ['password' => 'password'])->assertStatus(405);
        $this->assertNotNull($user->fresh());
    }
}
