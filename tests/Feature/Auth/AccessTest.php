<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_see_home_availability_and_login(): void
    {
        $this->get('/')->assertOk()->assertSee('Book Now');
        $this->get('/availability')->assertOk();
        $this->get('/login')->assertOk();
    }

    public function test_visitors_are_sent_to_login_for_profile_and_admin(): void
    {
        $this->get('/profile')->assertRedirect(route('login'));
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_guest_cannot_open_the_admin_area(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_can_open_the_admin_area(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()->assertSee('Dashboard');
    }

    public function test_nav_shows_the_right_links_for_each_kind_of_user(): void
    {
        $this->get('/')->assertSee('Log in')->assertSee('Create account')->assertDontSee('Profile');

        $this->actingAs(User::factory()->create())->get('/')
            ->assertSee('Profile')->assertSee('Log out')->assertDontSee('Dashboard');

        $this->actingAs(User::factory()->admin()->create())->get('/')
            ->assertSee('Dashboard')->assertSee('Profile');
    }

    public function test_a_guest_deactivated_while_logged_in_is_signed_out_on_the_next_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
