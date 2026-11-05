<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function valid(array $over = []): array
    {
        return array_merge([
            'name' => 'Maria Santos',
            'phone' => '09171234567',
            'email' => 'Maria@Example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $over);
    }

    public function test_register_page_is_visible_to_visitors(): void
    {
        $this->get('/register')->assertOk()->assertSee('Create your account');
    }

    public function test_guest_can_register_and_is_logged_in(): void
    {
        $this->post('/register', $this->valid())->assertRedirect(route('availability'));

        $user = User::where('email', 'maria@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('09171234567', $user->phone);
        $this->assertSame(UserRole::Guest, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_cannot_create_an_admin(): void
    {
        $this->post('/register', $this->valid(['role' => 'admin', 'is_active' => 0]));

        $user = User::where('email', 'maria@example.test')->first();
        $this->assertSame(UserRole::Guest, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_registration_is_recorded_in_the_activity_log(): void
    {
        $this->post('/register', $this->valid());
        $user = User::where('email', 'maria@example.test')->first();

        $this->assertDatabaseHas('activity_logs', ['event' => 'registered', 'user_id' => $user->id, 'actor_type' => 'guest', 'category' => 'account']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'login', 'user_id' => $user->id]);
    }

    public function test_contact_number_is_required(): void
    {
        $this->post('/register', $this->valid(['phone' => '']))->assertSessionHasErrors('phone');
        $this->post('/register', $this->valid(['phone' => 'abc']))->assertSessionHasErrors('phone');
        $this->assertSame(0, User::count());
    }

    public function test_duplicate_email_short_password_and_mismatch_are_refused(): void
    {
        User::factory()->create(['email' => 'maria@example.test']);

        $this->post('/register', $this->valid())->assertSessionHasErrors('email');
        $this->post('/register', $this->valid(['email' => 'new@example.test', 'password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post('/register', $this->valid(['email' => 'new@example.test', 'password_confirmation' => 'different1']))->assertSessionHasErrors('password');
    }

    public function test_failed_registration_writes_no_log(): void
    {
        $this->post('/register', $this->valid(['phone' => '']));

        $this->assertSame(0, ActivityLog::count());
    }
}
