<?php

namespace Tests\Feature\Reservation;

use App\Enums\ActorType;
use App\Enums\ReservationStatus;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyReservationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_sees_only_their_own_reservations_newest_date_first(): void
    {
        $guest = User::factory()->create();
        $mine = Reservation::factory()->count(2)->for($guest)->create()->sortBy('reservation_date')->values();
        [$older, $newer] = [$mine[0], $mine[1]];
        $other = Reservation::factory()->create(['occasion' => 'SomeoneElsesParty']);

        $response = $this->actingAs($guest)->get(route('reservations.index'))->assertOk();

        $response->assertDontSee('SomeoneElsesParty');
        $response->assertSeeInOrder([
            $newer->reservation_date->format('F j, Y'),
            $older->reservation_date->format('F j, Y'),
        ]);
        $response->assertSee('Slot Held');
    }

    public function test_the_empty_state_and_access_rules(): void
    {
        $this->get(route('reservations.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->admin()->create())->get(route('reservations.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('reservations.index'))->assertOk()->assertSee('You have no reservations yet.');
    }

    public function test_the_nav_shows_my_reservations_to_guests_only(): void
    {
        $this->actingAs(User::factory()->create())->get(route('availability'))->assertSee('My Reservations');
        $this->actingAs(User::factory()->admin()->create())->get(route('availability'))->assertDontSee('My Reservations');
    }

    public function test_a_guest_can_cancel_a_held_reservation_and_the_date_reopens(): void
    {
        $reservation = Reservation::factory()->create();
        $date = $reservation->reservation_date->toDateString();

        $this->actingAs($reservation->user)
            ->post(route('reservations.cancel', $reservation))
            ->assertRedirect(route('reservations.index'));

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertNull($reservation->blocking_date);
        $this->assertNull($reservation->hold_expires_at);

        // Another guest can now book the same date.
        $this->actingAs(User::factory()->create())->post('/reservations', [
            'reservation_date' => $date,
            'duration' => 'whole_day',
            'booking_type' => 'pool',
            'pax' => 10,
            'occasion' => 'Reunion',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Reservation::whereDate('blocking_date', $date)->count());
    }

    public function test_cancelling_is_logged_with_the_guest_as_actor(): void
    {
        $reservation = Reservation::factory()->create();
        $this->actingAs($reservation->user)->post(route('reservations.cancel', $reservation));

        $log = ActivityLog::where('event', 'cancelled')->firstOrFail();
        $this->assertSame(ActorType::Guest, $log->actor_type);
        $this->assertSame($reservation->user_id, $log->actor_id);
        $this->assertSame($reservation->id, $log->reservation_id);
    }

    public function test_only_the_owner_can_cancel(): void
    {
        $reservation = Reservation::factory()->create();
        $url = route('reservations.cancel', $reservation);

        $this->post($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post($url)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post($url)->assertForbidden();
        $this->assertSame(ReservationStatus::SlotHeld, $reservation->fresh()->status);
    }

    public function test_cancel_is_refused_in_every_other_status_and_changes_nothing(): void
    {
        $cases = [
            ['status' => ReservationStatus::VerifyingPayment, 'hold_expires_at' => null],
            ['status' => ReservationStatus::ActionNeeded, 'hold_expires_at' => null],
            ['status' => ReservationStatus::Confirmed, 'hold_expires_at' => null],
            ['status' => ReservationStatus::SlotHeld, 'hold_expires_at' => now()->subMinute()], // hold already ended
        ];

        foreach ($cases as $state) {
            $reservation = Reservation::factory()->create($state);

            $this->actingAs($reservation->user)
                ->post(route('reservations.cancel', $reservation))
                ->assertRedirect(route('reservations.show', $reservation))
                ->assertSessionHas('error');

            $fresh = $reservation->fresh();
            $this->assertSame($state['status'], $fresh->status);
            $this->assertNotNull($fresh->blocking_date);
        }

        $this->assertSame(0, ActivityLog::where('event', 'cancelled')->count());
    }

    public function test_a_second_cancel_click_does_nothing(): void
    {
        $reservation = Reservation::factory()->create();
        $this->actingAs($reservation->user);

        $this->post(route('reservations.cancel', $reservation))->assertRedirect(route('reservations.index'));
        $this->post(route('reservations.cancel', $reservation))->assertSessionHas('error');

        $this->assertSame(1, ActivityLog::where('event', 'cancelled')->count());
    }

    public function test_the_cancel_button_shows_only_while_it_is_allowed(): void
    {
        $held = Reservation::factory()->create();
        $this->actingAs($held->user)->get(route('reservations.show', $held))->assertSee('Cancel this reservation');

        $confirmed = Reservation::factory()->create(['status' => ReservationStatus::Confirmed, 'hold_expires_at' => null]);
        $this->actingAs($confirmed->user)->get(route('reservations.show', $confirmed))->assertDontSee('Cancel this reservation');
    }
}
