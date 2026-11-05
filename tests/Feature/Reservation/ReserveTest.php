<?php

namespace Tests\Feature\Reservation;

use App\Enums\ActorType;
use App\Enums\BookingType;
use App\Enums\Duration;
use App\Enums\LogCategory;
use App\Enums\ReservationStatus;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReserveTest extends TestCase
{
    use RefreshDatabase;

    private function date(int $daysAhead = 10): string
    {
        return now()->addDays($daysAhead)->toDateString();
    }

    private function valid(array $over = []): array
    {
        return array_merge([
            'reservation_date' => $this->date(),
            'duration' => 'whole_day',
            'booking_type' => 'pool',
            'pax' => 20,
            'occasion' => 'Birthday',
        ], $over);
    }

    public function test_guest_sees_the_form_for_an_open_date(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/reserve?date='.$this->date())
            ->assertOk()
            ->assertSee('Whole Day')->assertSee('Half Day')
            ->assertSee('Pool')->assertSee('Transient')->assertSee('Room')->assertSee('Venue Hall')
            ->assertSee('Up to 7 guests')->assertSee('Up to 12 guests');
    }

    public function test_guest_submits_and_gets_a_slot_held_reservation_with_a_24_hour_hold(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/reservations', $this->valid());

        $reservation = Reservation::firstOrFail();
        $response->assertRedirect(route('reservations.show', $reservation));

        $this->assertSame($user->id, $reservation->user_id);
        $this->assertFalse($reservation->is_walk_in);
        $this->assertSame(ReservationStatus::SlotHeld, $reservation->status);
        $this->assertSame(Duration::WholeDay, $reservation->duration);
        $this->assertSame(BookingType::Pool, $reservation->booking_type);
        $this->assertSame(20, $reservation->pax);
        $this->assertSame($this->date(), $reservation->blocking_date->toDateString());
        $this->assertEqualsWithDelta(24 * 60, now()->diffInMinutes($reservation->hold_expires_at), 2);
    }

    public function test_submitting_is_logged_as_submitted_by_the_guest(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/reservations', $this->valid());

        $log = ActivityLog::where('event', 'submitted')->firstOrFail();
        $this->assertSame(LogCategory::Reservation, $log->category);
        $this->assertSame(ActorType::Guest, $log->actor_type);
        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(Reservation::firstOrFail()->id, $log->reservation_id);
    }

    public function test_name_and_status_cannot_be_set_from_the_form(): void
    {
        $this->actingAs(User::factory()->create())->post('/reservations', $this->valid([
            'guest_name' => 'Someone Else',
            'is_walk_in' => 1,
            'status' => 'confirmed',
        ]));

        $reservation = Reservation::firstOrFail();
        $this->assertNull($reservation->guest_name);
        $this->assertFalse($reservation->is_walk_in);
        $this->assertSame(ReservationStatus::SlotHeld, $reservation->status);
    }

    public function test_transient_and_room_pax_caps_are_enforced(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/reservations', $this->valid(['booking_type' => 'transient', 'pax' => 8]))
            ->assertSessionHasErrors('pax');
        $this->actingAs($user)->post('/reservations', $this->valid(['booking_type' => 'room', 'pax' => 13]))
            ->assertSessionHasErrors('pax');
        $this->assertSame(0, Reservation::count());

        $this->actingAs($user)->post('/reservations', $this->valid(['booking_type' => 'transient', 'pax' => 7]))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/reservations', $this->valid(['booking_type' => 'room', 'pax' => 12, 'reservation_date' => $this->date(11)]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Reservation::count());
    }

    public function test_pool_and_venue_hall_have_no_pax_limit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/reservations', $this->valid(['booking_type' => 'pool', 'pax' => 500]))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/reservations', $this->valid(['booking_type' => 'venue_hall', 'pax' => 500, 'reservation_date' => $this->date(11)]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Reservation::count());
    }

    public function test_invalid_fields_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())->post('/reservations', [])
            ->assertSessionHasErrors(['reservation_date', 'duration', 'booking_type', 'pax', 'occasion']);

        $this->post('/reservations', $this->valid(['duration' => 'night', 'booking_type' => 'suite', 'pax' => 0]))
            ->assertSessionHasErrors(['duration', 'booking_type', 'pax']);
        $this->assertSame(0, Reservation::count());
    }

    public function test_online_booking_starts_tomorrow_not_today_or_the_past(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/reservations', $this->valid(['reservation_date' => today()->toDateString()]))
            ->assertSessionHasErrors('reservation_date');
        $this->actingAs($user)->post('/reservations', $this->valid(['reservation_date' => today()->subDay()->toDateString()]))
            ->assertSessionHasErrors('reservation_date');
        $this->assertSame(0, Reservation::count());

        $this->actingAs($user)->post('/reservations', $this->valid(['reservation_date' => today()->addDay()->toDateString()]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Reservation::count());
    }

    public function test_min_advance_days_comes_from_config(): void
    {
        config(['zk.min_advance_days' => 0]);

        $this->actingAs(User::factory()->create())
            ->post('/reservations', $this->valid(['reservation_date' => today()->toDateString()]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Reservation::count());
    }

    public function test_a_blocked_date_cannot_be_booked_and_the_form_sends_the_guest_back(): void
    {
        $date = $this->date();
        Reservation::factory()->create(['reservation_date' => $date, 'blocking_date' => $date]);
        $guest = User::factory()->create();

        $this->actingAs($guest)->get('/reserve?date='.$date)
            ->assertRedirect(route('availability', ['month' => substr($date, 0, 7)]))
            ->assertSessionHas('error');

        $this->actingAs($guest)->post('/reservations', $this->valid())
            ->assertRedirect(route('availability', ['month' => substr($date, 0, 7)]))
            ->assertSessionHas('error');
        $this->assertSame(1, Reservation::count());
    }

    public function test_a_released_date_can_be_booked_again(): void
    {
        $date = $this->date();
        Reservation::factory()->released(ReservationStatus::Cancelled)->create(['reservation_date' => $date]);

        $this->actingAs(User::factory()->create())->post('/reservations', $this->valid())
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Reservation::whereNotNull('blocking_date')->count());
    }

    public function test_form_with_a_missing_or_bad_date_goes_back_to_the_calendar(): void
    {
        $guest = User::factory()->create();

        $this->actingAs($guest)->get('/reserve')->assertRedirect(route('availability'));
        $this->actingAs($guest)->get('/reserve?date=2026-02-30')->assertRedirect(route('availability'));
        $this->actingAs($guest)->get('/reserve?date='.today()->toDateString())->assertRedirect(route('availability'));
    }

    public function test_admin_cannot_use_the_guest_form(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/reserve?date='.$this->date())->assertForbidden();
        $this->actingAs($admin)->post('/reservations', $this->valid())->assertForbidden();
        $this->assertSame(0, Reservation::count());
    }

    public function test_visitors_and_deactivated_guests_cannot_submit(): void
    {
        $this->post('/reservations', $this->valid())->assertRedirect(route('login'));

        $this->actingAs(User::factory()->deactivated()->create())
            ->post('/reservations', $this->valid())->assertRedirect(route('login'));
        $this->assertSame(0, Reservation::count());
    }

    public function test_owner_sees_the_reservation_with_status_and_countdown(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/reservations', $this->valid());
        $reservation = Reservation::firstOrFail();

        $this->get(route('reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Slot Held')
            ->assertSee('Time left to hold this date')
            ->assertSee('data-countdown', false)
            ->assertSee('Birthday');
    }

    public function test_only_the_owner_can_open_a_reservation(): void
    {
        $reservation = Reservation::factory()->create();

        $this->get(route('reservations.show', $reservation))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('reservations.show', $reservation))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('reservations.show', $reservation))->assertForbidden();
        $this->actingAs($reservation->user)->get(route('reservations.show', $reservation))->assertOk();
    }
}
