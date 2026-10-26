<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_members_can_visit_the_dashboard()
    {
        $this->actingAs($this->member()->user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('dashboard')->where('finance', null));
    }

    public function test_officials_see_group_finances()
    {
        $this->actingAs($this->official('treasurer'));

        $this->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->has('finance.accounts', 10));
    }

    public function test_logins_not_linked_to_a_member_are_refused()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertForbidden();
    }
}
