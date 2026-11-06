<?php

namespace Tests\Feature\Admin;

use App\Enums\ActorType;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['name' => 'Resort Admin']);
    }

    public function test_only_the_admin_can_open_it(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
    }

    public function test_it_lists_guests_only_with_contact_email_and_state(): void
    {
        User::factory()->create(['name' => 'Maria Santos', 'phone' => '09171234567', 'email' => 'maria@example.com']);
        User::factory()->deactivated()->create(['name' => 'Juan Cruz']);

        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Maria Santos')->assertSee('09171234567')->assertSee('maria@example.com')
            ->assertSee('Active')->assertSee('Deactivated')
            ->assertDontSee('Resort Admin');
    }

    public function test_search_by_name_and_empty_state(): void
    {
        User::factory()->create(['name' => 'Maria Santos']);
        User::factory()->create(['name' => 'Juan Cruz']);

        $this->actingAs($this->admin)->get(route('admin.users.index', ['q' => 'Maria']))
            ->assertSee('Maria Santos')->assertDontSee('Juan Cruz');
        $this->get(route('admin.users.index', ['q' => 'Nobody']))->assertSee('No guest accounts found.');
    }

    public function test_it_pages_at_15(): void
    {
        User::factory()->count(20)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
        $this->assertCount(15, $response->viewData('users'));
        $this->assertSame(20, $response->viewData('users')->total());
    }

    public function test_deactivating_blocks_the_guest_and_is_logged_by_the_admin(): void
    {
        $guest = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.users.deactivate', $guest))
            ->assertRedirect(route('admin.users.index'))->assertSessionHas('status');

        $this->assertFalse($guest->fresh()->is_active);

        $log = ActivityLog::where('event', 'deactivated')->firstOrFail();
        $this->assertSame(ActorType::Admin, $log->actor_type);
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame($guest->id, $log->user_id);
    }

    public function test_a_deactivated_guest_cannot_log_in_and_a_logged_in_one_is_signed_out(): void
    {
        $guest = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.users.deactivate', $guest));

        $this->actingAs($guest->fresh())->get(route('availability'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_reactivating_lets_them_back_in_and_is_logged(): void
    {
        $guest = User::factory()->deactivated()->create();

        $this->actingAs($this->admin)->post(route('admin.users.reactivate', $guest))->assertSessionHas('status');

        $this->assertTrue($guest->fresh()->is_active);
        $log = ActivityLog::where('event', 'reactivated')->firstOrFail();
        $this->assertSame(ActorType::Admin, $log->actor_type);
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame($guest->id, $log->user_id);
    }

    public function test_repeating_an_action_changes_and_logs_nothing(): void
    {
        $guest = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.users.reactivate', $guest))->assertSessionHas('error');

        $this->assertSame(0, ActivityLog::where('event', 'reactivated')->count());
    }

    public function test_the_admin_account_cannot_be_changed_here(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.deactivate', $this->admin))->assertNotFound();

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_guests_cannot_deactivate_anyone(): void
    {
        $other = User::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('admin.users.deactivate', $other))->assertForbidden();

        $this->assertTrue($other->fresh()->is_active);
    }

    public function test_there_is_no_delete_edit_or_create(): void
    {
        $guest = User::factory()->create();
        $this->actingAs($this->admin);

        // No route exists for these, so they are not found (or not allowed on the list URL).
        $this->delete('/admin/users/'.$guest->id)->assertNotFound();
        $this->put('/admin/users/'.$guest->id)->assertNotFound();
        $this->post('/admin/users')->assertStatus(405);
        $this->assertNotNull($guest->fresh());
    }

    public function test_existing_reservations_are_left_alone_and_a_guest_filter_shows_one_guests_reservations(): void
    {
        $a = Reservation::factory()->create();
        $b = Reservation::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.users.deactivate', $a->user));

        $this->assertNotNull($a->fresh()->blocking_date);

        $this->get(route('admin.dashboard', ['guest' => $a->user_id]))
            ->assertSee('Showing one guest')->assertSee($a->user->name)->assertDontSee($b->user->name);

        $this->get(route('admin.users.index'))
            ->assertSee(route('admin.dashboard', ['guest' => $a->user_id]), false)
            ->assertSee(route('admin.activity-log', ['guest' => $a->user_id]), false);
    }

    public function test_the_nav_link_is_for_the_admin_only(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('User List');
        $this->actingAs(User::factory()->create())->get(route('availability'))->assertDontSee('User List');
    }
}
