<?php

namespace Tests\Feature\Reservation;

use App\Enums\ActorType;
use App\Enums\ChangeRequest;
use App\Enums\ReservationStatus;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    private function reservation(ReservationStatus $status = ReservationStatus::Confirmed, array $over = []): Reservation
    {
        return Reservation::factory()->create(array_merge(['status' => $status, 'hold_expires_at' => null], $over));
    }

    /** A reservation whose date is inside the notice window. */
    private function tooClose(): Reservation
    {
        $date = now()->addDays((int) config('zk.notice_days') - 1)->toDateString();

        return $this->reservation(ReservationStatus::Confirmed, ['reservation_date' => $date, 'blocking_date' => $date]);
    }

    public function test_a_guest_can_request_a_cancellation_in_each_allowed_status(): void
    {
        foreach ([ReservationStatus::VerifyingPayment, ReservationStatus::ActionNeeded, ReservationStatus::Confirmed] as $status) {
            $r = $this->reservation($status);

            $this->actingAs($r->user)->post(route('reservations.request-cancel', $r))
                ->assertRedirect(route('reservations.show', $r))
                ->assertSessionHas('status');

            $r->refresh();
            $this->assertSame(ChangeRequest::Cancel, $r->change_request);
            $this->assertNotNull($r->change_requested_at);
            $this->assertSame($status, $r->status);        // status does not change
            $this->assertNotNull($r->blocking_date);        // the date stays blocked
        }

        $this->assertSame(3, ActivityLog::where('event', 'cancel_requested')->where('actor_type', ActorType::Guest)->count());
    }

    public function test_a_guest_can_request_a_reschedule_to_a_free_date(): void
    {
        $r = $this->reservation();
        $new = now()->addDays(500)->toDateString();

        $this->actingAs($r->user)->post(route('reservations.request-reschedule', $r), ['new_date' => $new])
            ->assertRedirect(route('reservations.show', $r))
            ->assertSessionHas('status');

        $r->refresh();
        $this->assertSame(ChangeRequest::Reschedule, $r->change_request);
        $this->assertSame($new, $r->requested_date->toDateString());
        $this->assertNotNull($r->blocking_date); // the old date stays blocked
        $this->assertFalse(Reservation::whereDate('blocking_date', $new)->exists()); // the new date is not blocked yet

        $log = ActivityLog::where('event', 'reschedule_requested')->firstOrFail();
        $this->assertSame(ActorType::Guest, $log->actor_type);
        $this->assertSame($r->id, $log->reservation_id);
    }

    public function test_requests_inside_the_notice_window_are_blocked_and_nothing_changes(): void
    {
        $r = $this->tooClose();

        $this->actingAs($r->user)->post(route('reservations.request-cancel', $r))->assertSessionHas('error');
        $this->actingAs($r->user)->post(route('reservations.request-reschedule', $r), ['new_date' => now()->addDays(500)->toDateString()])
            ->assertSessionHasErrors('new_date');

        $this->assertNull($r->fresh()->change_request);
        $this->assertSame(0, ActivityLog::whereIn('event', ['cancel_requested', 'reschedule_requested'])->count());

        $this->actingAs($r->user)->get(route('reservations.show', $r))->assertSee('please contact the resort')->assertDontSee('Request cancellation');
    }

    public function test_the_notice_window_comes_from_config(): void
    {
        $r = $this->tooClose(); // inside 5 days
        config(['zk.notice_days' => 1]);

        $this->actingAs($r->user)->post(route('reservations.request-cancel', $r))->assertSessionHas('status');
    }

    public function test_a_reschedule_needs_a_confirmed_reservation(): void
    {
        foreach ([ReservationStatus::VerifyingPayment, ReservationStatus::ActionNeeded, ReservationStatus::SlotHeld] as $status) {
            $r = $this->reservation($status, $status === ReservationStatus::SlotHeld ? ['hold_expires_at' => now()->addHour()] : []);

            $this->actingAs($r->user)->post(route('reservations.request-reschedule', $r), ['new_date' => now()->addDays(500)->toDateString()])
                ->assertSessionHasErrors('new_date');

            $this->assertNull($r->fresh()->change_request);
        }
    }

    public function test_slot_held_expired_and_cancelled_cannot_request_a_cancellation(): void
    {
        $cases = [
            $this->reservation(ReservationStatus::SlotHeld, ['hold_expires_at' => now()->addHour()]),
            Reservation::factory()->released(ReservationStatus::Expired)->create(),
            Reservation::factory()->released(ReservationStatus::Cancelled)->create(),
        ];

        foreach ($cases as $r) {
            $this->actingAs($r->user)->post(route('reservations.request-cancel', $r))->assertSessionHas('error');
            $this->assertNull($r->fresh()->change_request);
        }
    }

    public function test_the_new_date_is_checked(): void
    {
        $r = $this->reservation();
        $taken = $this->reservation();
        $this->actingAs($r->user);
        $url = route('reservations.request-reschedule', $r);

        $this->post($url, [])->assertSessionHasErrors('new_date');
        $this->post($url, ['new_date' => 'tomorrow-ish'])->assertSessionHasErrors('new_date');
        $this->post($url, ['new_date' => now()->subDay()->toDateString()])->assertSessionHasErrors('new_date');
        $this->post($url, ['new_date' => today()->toDateString()])->assertSessionHasErrors('new_date');          // before the earliest bookable date
        $this->post($url, ['new_date' => $r->reservation_date->toDateString()])->assertSessionHasErrors('new_date'); // same date
        $this->post($url, ['new_date' => $taken->reservation_date->toDateString()])->assertSessionHasErrors('new_date'); // taken

        $this->assertNull($r->fresh()->change_request);
    }

    public function test_only_one_request_can_be_pending(): void
    {
        $r = $this->reservation();
        $this->actingAs($r->user);

        $this->post(route('reservations.request-cancel', $r))->assertSessionHas('status');
        $this->post(route('reservations.request-cancel', $r))->assertSessionHas('error');
        $this->post(route('reservations.request-reschedule', $r), ['new_date' => now()->addDays(500)->toDateString()])->assertSessionHasErrors('new_date');

        $this->assertSame(ChangeRequest::Cancel, $r->fresh()->change_request);
        $this->assertSame(1, ActivityLog::where('event', 'cancel_requested')->count());
    }

    public function test_only_the_owner_can_request(): void
    {
        $r = $this->reservation();
        $new = ['new_date' => now()->addDays(500)->toDateString()];

        $this->post(route('reservations.request-cancel', $r))->assertRedirect(route('login'));
        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $other) {
            $this->actingAs($other)->post(route('reservations.request-cancel', $r))->assertForbidden();
            $this->actingAs($other)->post(route('reservations.request-reschedule', $r), $new)->assertForbidden();
        }

        $this->assertNull($r->fresh()->change_request);
    }

    public function test_the_page_offers_the_right_buttons_and_shows_a_pending_request(): void
    {
        $confirmed = $this->reservation();
        $this->actingAs($confirmed->user)->get(route('reservations.show', $confirmed))
            ->assertSee('Request reschedule')->assertSee('Request cancellation');

        $verifying = $this->reservation(ReservationStatus::VerifyingPayment);
        $this->actingAs($verifying->user)->get(route('reservations.show', $verifying))
            ->assertDontSee('Request reschedule')->assertSee('Request cancellation');

        $this->actingAs($confirmed->user)->post(route('reservations.request-cancel', $confirmed));
        $this->actingAs($confirmed->user)->get(route('reservations.show', $confirmed))
            ->assertSee('Cancellation requested')->assertDontSee('Request cancellation');
        $this->actingAs($confirmed->user)->get(route('reservations.index'))->assertSee('Cancellation requested');
    }
}
