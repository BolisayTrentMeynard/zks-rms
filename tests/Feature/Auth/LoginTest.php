<?php

namespace Tests\Feature\Auth;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_visible_to_visitors(): void
    {
        $this->get('/login')->assertOk()->assertSee('Log in');
    }

    public function test_guest_logs_in_and_lands_on_availability(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('availability'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_logs_in_through_the_same_page_and_lands_on_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_email_is_matched_ignoring_case_and_spaces(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.test']);

        $this->post('/login', ['email' => '  Jane@Example.test ', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_refused_and_not_logged(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, ActivityLog::count()); // failed logins are not recorded
    }

    public function test_deactivated_guest_cannot_log_in(): void
    {
        $user = User::factory()->deactivated()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'This account is deactivated. Please contact the resort.']);

        $this->assertGuest();
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_deactivated_guest_with_wrong_password_gets_the_normal_error(): void
    {
        $user = User::factory()->deactivated()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }

    public function test_login_and_logout_are_recorded_with_the_right_actor(): void
    {
        $guest = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->post('/login', ['email' => $guest->email, 'password' => 'password']);
        $this->post('/logout');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/logout');

        $this->assertDatabaseHas('activity_logs', ['event' => 'login', 'user_id' => $guest->id, 'actor_type' => 'guest']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'logout', 'user_id' => $guest->id, 'actor_type' => 'guest']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'login', 'user_id' => $admin->id, 'actor_type' => 'admin']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'logout', 'user_id' => $admin->id, 'actor_type' => 'admin']);
    }

    public function test_logout_signs_the_user_out_and_goes_home(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_logged_in_users_are_sent_away_from_login_and_register(): void
    {
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect(route('availability'));
        $this->actingAs(User::factory()->admin()->create())->get('/register')->assertRedirect(route('admin.dashboard'));
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
