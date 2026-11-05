<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_shows_name_contact_and_email(): void
    {
        $user = User::factory()->create(['name' => 'Maria Santos', 'phone' => '09171234567']);

        $this->actingAs($user)->get('/profile')
            ->assertOk()->assertSee('Maria Santos')->assertSee('09171234567')->assertSee($user->email);
    }

    public function test_guest_can_edit_their_own_profile_and_it_is_logged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile', [
            'name' => 'New Name', 'phone' => '09998887777', 'email' => 'new@example.test',
        ])->assertRedirect(route('profile.edit'))->assertSessionHas('status', 'Profile saved.');

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('09998887777', $user->phone);
        $this->assertSame('new@example.test', $user->email);
        $this->assertDatabaseHas('activity_logs', ['event' => 'profile_edited', 'user_id' => $user->id, 'actor_type' => 'guest']);
    }

    public function test_saving_without_changes_writes_no_log(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile', ['name' => $user->name, 'phone' => $user->phone, 'email' => $user->email]);

        $this->assertDatabaseMissing('activity_logs', ['event' => 'profile_edited']);
    }

    public function test_admin_can_edit_their_profile_too(): void
    {
        $admin = User::factory()->admin()->create(['phone' => null]);

        $this->actingAs($admin)->put('/profile', ['name' => 'Resort Admin', 'phone' => '09123456789', 'email' => $admin->email])
            ->assertSessionHasNoErrors();

        $this->assertSame('09123456789', $admin->fresh()->phone);
        $this->assertDatabaseHas('activity_logs', ['event' => 'profile_edited', 'user_id' => $admin->id, 'actor_type' => 'admin']);
    }

    public function test_email_must_stay_unique_but_own_email_is_allowed(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile', ['name' => 'X Y', 'phone' => '09171234567', 'email' => 'taken@example.test'])
            ->assertSessionHasErrors('email');
        $this->actingAs($user)->put('/profile', ['name' => 'X Y', 'phone' => '09171234567', 'email' => $user->email])
            ->assertSessionHasNoErrors();
    }

    public function test_role_cannot_be_changed_through_the_profile_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile', [
            'name' => 'X Y', 'phone' => '09171234567', 'email' => $user->email, 'role' => 'admin', 'is_active' => 0,
        ]);

        $this->assertTrue($user->fresh()->isGuest());
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_profile_needs_a_valid_contact_number(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile', ['name' => 'X Y', 'phone' => '', 'email' => $user->email])
            ->assertSessionHasErrors('phone');
    }
}
