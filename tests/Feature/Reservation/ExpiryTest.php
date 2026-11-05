<?php

namespace Tests\Feature\Reservation;

use App\Enums\ActorType;
use App\Enums\ReservationStatus;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_due_hold_expires_the_date_reopens_and_it_is_logged_by_the_system(): void
    {
        $due = Reservation::factory()->create(['hold_expires_at' => now()->subMinute()]);
        $date = $due->reservation_date->toDateString();

        $this->artisan('reservations:expire-holds')->assertSuccessful();

        $due->refresh();
        $this->assertSame(ReservationStatus::Expired, $due->status);
        $this->assertNull($due->blocking_date);

        $log = ActivityLog::where('event', 'expired')->firstOrFail();
        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->actor_id);
        $this->assertSame($due->id, $log->reservation_id);
        $this->assertSame($due->user_id, $log->user_id);

        // The date can be booked again.
        $this->actingAs(User::factory()->create())->post('/reservations', [
            'reservation_date' => $date,
            'duration' => 'whole_day',
            'booking_type' => 'pool',
            'pax' => 10,
            'occasion' => 'Reunion',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Reservation::whereDate('blocking_date', $date)->count());
    }

    public function test_only_due_slot_held_reservations_are_touched(): void
    {
        $notDue = Reservation::factory()->create(['hold_expires_at' => now()->addHour()]);
        $verifying = Reservation::factory()->create(['status' => ReservationStatus::VerifyingPayment, 'hold_expires_at' => null]);
        $flagged = Reservation::factory()->create(['status' => ReservationStatus::ActionNeeded, 'hold_expires_at' => null]);
        $confirmed = Reservation::factory()->create(['status' => ReservationStatus::Confirmed, 'hold_expires_at' => null]);
        $due = Reservation::factory()->create(['hold_expires_at' => now()->subSecond()]);

        $this->artisan('reservations:expire-holds')->assertSuccessful();

        $this->assertSame(ReservationStatus::SlotHeld, $notDue->fresh()->status);
        $this->assertSame(ReservationStatus::VerifyingPayment, $verifying->fresh()->status);
        $this->assertSame(ReservationStatus::ActionNeeded, $flagged->fresh()->status);
        $this->assertSame(ReservationStatus::Confirmed, $confirmed->fresh()->status);
        $this->assertSame(ReservationStatus::Expired, $due->fresh()->status);

        foreach ([$notDue, $verifying, $flagged, $confirmed] as $kept) {
            $this->assertNotNull($kept->fresh()->blocking_date);
        }
    }

    public function test_running_it_twice_is_safe(): void
    {
        Reservation::factory()->create(['hold_expires_at' => now()->subMinute()]);

        $this->artisan('reservations:expire-holds')->assertSuccessful();
        $this->artisan('reservations:expire-holds')->assertSuccessful();

        $this->assertSame(1, ActivityLog::where('event', 'expired')->count());
    }

    public function test_the_command_is_scheduled_every_minute(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('reservations:expire-holds')
            ->assertSuccessful();
    }
}
