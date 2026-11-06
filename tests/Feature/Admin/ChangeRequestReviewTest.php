<?php

namespace Tests\Feature\Admin;

use App\Enums\ActorType;
use App\Enums\ChangeRequest;
use App\Enums\ReservationStatus;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeRequestReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function withRequest(ChangeRequest $type, ?string $newDate = null): Reservation
    {
        return Reservation::factory()->create([
            'status' => ReservationStatus::Confirmed,
            'hold_expires_at' => null,
            'change_request' => $type,
            'requested_date' => $newDate,
            'change_requested_at' => now(),
        ]);
    }

    public function test_the_detail_page_shows_the_request_and_both_buttons(): void
    {
        $new = now()->addDays(500)->toDateString();
        $r = $this->withRequest(ChangeRequest::Reschedule, $new);

        $this->actingAs($this->admin)->get(route('admin.reservations.show', $r))
            ->assertOk()
            ->assertSee('Reschedule requested')
            ->assertSee(now()->addDays(500)->format('l, F j, Y'))
            ->assertSee('Approve request')
            ->assertSee('Decline request');
    }

    public function test_approving_a_cancellation_cancels_and_releases_the_date(): void
    {
        $r = $this->withRequest(ChangeRequest::Cancel);
        $date = $r->reservation_date->toDateString();

        $this->actingAs($this->admin)->post(route('admin.reservations.approve', $r))
            ->assertRedirect(route('admin.reservations.show', $r))
            ->assertSessionHas('status');

        $r->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $r->status);
        $this->assertNull($r->blocking_date);
        $this->assertNull($r->change_request);
        $this->assertFalse($r->downpayment_forfeited);
        $this->assertFalse(Reservation::whereDate('blocking_date', $date)->exists());

        $log = ActivityLog::where('event', 'cancelled')->firstOrFail();
        $this->assertSame(ActorType::Admin, $log->actor_type);
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame($r->user_id, $log->user_id);
    }

    public function test_approving_a_reschedule_moves_the_date_and_releases_the_old_one(): void
    {
        $new = now()->addDays(500)->toDateString();
        $r = $this->withRequest(ChangeRequest::Reschedule, $new);
        $old = $r->reservation_date->toDateString();

        $this->actingAs($this->admin)->post(route('admin.reservations.approve', $r))->assertSessionHas('status');

        $r->refresh();
        $this->assertSame(ReservationStatus::Confirmed, $r->status);
        $this->assertSame($new, $r->reservation_date->toDateString());
        $this->assertSame($new, $r->blocking_date->toDateString());
        $this->assertNull($r->change_request);
        $this->assertNull($r->requested_date);
        $this->assertFalse(Reservation::whereDate('blocking_date', $old)->exists());
        $this->assertTrue(Reservation::whereDate('blocking_date', $new)->exists());

        $log = ActivityLog::where('event', 'rescheduled')->firstOrFail();
        $this->assertSame(ActorType::Admin, $log->actor_type);
    }

    public function test_approving_a_reschedule_fails_when_the_new_date_was_taken_meanwhile(): void
    {
        $new = now()->addDays(500)->toDateString();
        $r = $this->withRequest(ChangeRequest::Reschedule, $new);
        $old = $r->reservation_date->toDateString();
        Reservation::factory()->create(['reservation_date' => $new, 'blocking_date' => $new]); // someone else got it

        $this->actingAs($this->admin)->post(route('admin.reservations.approve', $r))->assertSessionHas('error');

        $r->refresh();
        $this->assertSame($old, $r->reservation_date->toDateString());
        $this->assertSame(ChangeRequest::Reschedule, $r->change_request); // still pending, Admin can decline
        $this->assertSame(0, ActivityLog::where('event', 'rescheduled')->count());

        $this->actingAs($this->admin)->get(route('admin.reservations.show', $r))->assertSee('has been taken since the request');
    }

    public function test_declining_clears_the_request_and_changes_nothing_else(): void
    {
        $r = $this->withRequest(ChangeRequest::Cancel);
        $date = $r->reservation_date->toDateString();

        $this->actingAs($this->admin)->post(route('admin.reservations.decline', $r))->assertSessionHas('status');

        $r->refresh();
        $this->assertNull($r->change_request);
        $this->assertNull($r->change_requested_at);
        $this->assertSame(ReservationStatus::Confirmed, $r->status);
        $this->assertSame($date, $r->blocking_date->toDateString());

        $log = ActivityLog::where('event', 'request_declined')->firstOrFail();
        $this->assertSame(ActorType::Admin, $log->actor_type);
    }

    public function test_with_no_request_approve_and_decline_do_nothing(): void
    {
        $r = Reservation::factory()->create(['status' => ReservationStatus::Confirmed, 'hold_expires_at' => null]);

        $this->actingAs($this->admin)->post(route('admin.reservations.approve', $r))->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('admin.reservations.decline', $r))->assertSessionHas('error');

        $this->assertSame(ReservationStatus::Confirmed, $r->fresh()->status);
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_a_second_approve_click_does_nothing(): void
    {
        $r = $this->withRequest(ChangeRequest::Cancel);
        $this->actingAs($this->admin);

        $this->post(route('admin.reservations.approve', $r))->assertSessionHas('status');
        $this->post(route('admin.reservations.approve', $r))->assertSessionHas('error');

        $this->assertSame(1, ActivityLog::where('event', 'cancelled')->count());
    }

    public function test_admin_can_cancel_any_active_reservation_even_inside_the_notice_window(): void
    {
        $close = now()->addDays(2)->toDateString();
        $r = Reservation::factory()->create([
            'status' => ReservationStatus::Confirmed, 'hold_expires_at' => null,
            'reservation_date' => $close, 'blocking_date' => $close,
        ]);

        $this->actingAs($this->admin)->post(route('admin.reservations.cancel', $r), ['downpayment_forfeited' => '1'])
            ->assertSessionHas('status');

        $r->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $r->status);
        $this->assertNull($r->blocking_date);
        $this->assertTrue($r->downpayment_forfeited);

        $log = ActivityLog::where('event', 'cancelled')->firstOrFail();
        $this->assertSame(ActorType::Admin, $log->actor_type);
        $this->assertStringContainsString('forfeited', $log->description);
    }

    public function test_admin_cancel_without_the_tick_does_not_forfeit_and_also_clears_a_pending_request(): void
    {
        $r = $this->withRequest(ChangeRequest::Reschedule, now()->addDays(500)->toDateString());

        $this->actingAs($this->admin)->post(route('admin.reservations.cancel', $r))->assertSessionHas('status');

        $r->refresh();
        $this->assertFalse($r->downpayment_forfeited);
        $this->assertNull($r->change_request);
        $this->assertNull($r->requested_date);
    }

    public function test_cancelling_a_slot_held_reservation_ignores_the_forfeit_flag(): void
    {
        $r = Reservation::factory()->create(); // Slot Held

        $this->actingAs($this->admin)->post(route('admin.reservations.cancel', $r), ['downpayment_forfeited' => '1'])->assertSessionHas('status');

        $this->assertSame(ReservationStatus::Cancelled, $r->fresh()->status);
        $this->assertFalse($r->fresh()->downpayment_forfeited);
    }

    public function test_already_released_reservations_cannot_be_cancelled_again(): void
    {
        foreach ([ReservationStatus::Expired, ReservationStatus::Cancelled] as $status) {
            $r = Reservation::factory()->released($status)->create();

            $this->actingAs($this->admin)->post(route('admin.reservations.cancel', $r))->assertSessionHas('error');
            $this->assertSame($status, $r->fresh()->status);
        }

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_the_admin_routes_are_admin_only(): void
    {
        $r = $this->withRequest(ChangeRequest::Cancel);

        $routes = ['admin.reservations.approve', 'admin.reservations.decline', 'admin.reservations.cancel'];

        // Logged-out checks first: actingAs() keeps the user logged in for the rest of the test.
        foreach ($routes as $route) {
            $this->post(route($route, $r))->assertRedirect(route('login'));
        }

        foreach ($routes as $route) {
            $this->actingAs($r->user)->post(route($route, $r))->assertForbidden();
        }

        $this->assertSame(ChangeRequest::Cancel, $r->fresh()->change_request);
        $this->assertSame(ReservationStatus::Confirmed, $r->fresh()->status);
    }
}
