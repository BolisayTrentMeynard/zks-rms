<?php

namespace Tests\Feature\Admin;

use App\Enums\ChangeRequest;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function booking(string $name, array $over = []): Reservation
    {
        return Reservation::factory()->create(array_merge(
            ['user_id' => User::factory()->create(['name' => $name])->id],
            $over,
        ));
    }

    public function test_only_the_admin_can_open_the_dashboard_and_reservation_pages(): void
    {
        $reservation = $this->booking('Ana Cruz');

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.reservations.show', $reservation))->assertRedirect(route('login'));

        $guest = User::factory()->create();
        $this->actingAs($guest)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($guest)->get(route('admin.reservations.show', $reservation))->assertForbidden();

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.reservations.show', $reservation))->assertOk();
    }

    public function test_the_verifying_payment_count_comes_first(): void
    {
        $this->booking('One', ['status' => ReservationStatus::VerifyingPayment, 'hold_expires_at' => null]);
        $this->booking('Two', ['status' => ReservationStatus::VerifyingPayment, 'hold_expires_at' => null]);
        $this->booking('Three'); // Slot Held

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        $this->assertSame(2, $response->viewData('verifyingCount'));
        $response->assertSeeInOrder(['Verifying Payment', 'Payment proofs waiting for you to check.', 'One']);
    }

    public function test_the_list_shows_account_bookings_and_walk_ins(): void
    {
        $this->booking('Ana Cruz');
        Reservation::factory()->create([
            'user_id' => null,
            'is_walk_in' => true,
            'guest_name' => 'Walter Walkin',
            'guest_contact' => '09171234567',
            'status' => ReservationStatus::Confirmed,
            'hold_expires_at' => null,
        ]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertSee('Ana Cruz')
            ->assertSee('Walter Walkin')
            ->assertSee('(walk-in)');
    }

    public function test_filter_by_status(): void
    {
        $this->booking('Held Holly');
        $this->booking('Verifying Vic', ['status' => ReservationStatus::VerifyingPayment, 'hold_expires_at' => null]);

        $this->actingAs($this->admin)->get(route('admin.dashboard', ['status' => 'verifying_payment']))
            ->assertSee('Verifying Vic')
            ->assertDontSee('Held Holly');
    }

    public function test_an_unknown_status_in_the_address_is_ignored(): void
    {
        $this->booking('Held Holly');

        $this->actingAs($this->admin)->get(route('admin.dashboard', ['status' => 'nonsense']))
            ->assertOk()
            ->assertSee('Held Holly');
    }

    public function test_search_by_guest_name_finds_accounts_and_walk_ins(): void
    {
        $this->booking('Maria Santos');
        $this->booking('Pedro Reyes');
        Reservation::factory()->create(['user_id' => null, 'is_walk_in' => true, 'guest_name' => 'Marian Walkin', 'guest_contact' => '0917']);

        $this->actingAs($this->admin)->get(route('admin.dashboard', ['q' => 'mari']))
            ->assertSee('Maria Santos')
            ->assertSee('Marian Walkin')
            ->assertDontSee('Pedro Reyes');
    }

    public function test_search_and_filter_work_together(): void
    {
        $this->booking('Maria Held');
        $this->booking('Maria Verifying', ['status' => ReservationStatus::VerifyingPayment, 'hold_expires_at' => null]);

        $this->actingAs($this->admin)->get(route('admin.dashboard', ['q' => 'Maria', 'status' => 'slot_held']))
            ->assertSee('Maria Held')
            ->assertDontSee('Maria Verifying');
    }

    public function test_an_empty_result_says_so(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard', ['q' => 'nobody']))
            ->assertSee('No reservations found.');
    }

    public function test_the_list_is_paged(): void
    {
        for ($i = 0; $i < 17; $i++) {
            $this->booking('Guest '.$i);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $this->assertCount(15, $response->viewData('reservations'));
        $this->assertSame(17, $response->viewData('reservations')->total());
    }

    public function test_a_pending_reschedule_or_cancel_request_shows_beside_the_badge(): void
    {
        $this->booking('Ana Cruz', [
            'status' => ReservationStatus::Confirmed,
            'hold_expires_at' => null,
            'change_request' => ChangeRequest::Cancel,
        ]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('Cancellation requested');
    }

    public function test_the_six_badge_words_are_used_in_the_status_filter(): void
    {
        $page = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        foreach (['Slot Held', 'Verifying Payment', 'Confirmed', 'Action Needed', 'Expired', 'Cancelled'] as $label) {
            $page->assertSee($label);
        }
    }
}
