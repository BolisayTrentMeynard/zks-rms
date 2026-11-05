<?php

namespace Tests\Feature;

use App\Enums\ActorType;
use App\Enums\BookingType;
use App\Enums\ChangeRequest;
use App\Enums\LogCategory;
use App\Enums\ReservationStatus;
use App\Models\ActivityLog;
use App\Models\PaymentProof;
use App\Models\Reservation;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\AdminSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class Chunk1SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_badge_labels_are_the_six_locked_words(): void
    {
        $labels = array_map(fn ($s) => $s->label(), ReservationStatus::cases());

        $this->assertSame(
            ['Slot Held', 'Verifying Payment', 'Confirmed', 'Action Needed', 'Expired', 'Cancelled'],
            $labels
        );
    }

    public function test_only_active_statuses_block_a_date(): void
    {
        $this->assertEqualsCanonicalizing(
            [ReservationStatus::SlotHeld, ReservationStatus::VerifyingPayment, ReservationStatus::Confirmed, ReservationStatus::ActionNeeded],
            ReservationStatus::blocking()
        );
    }

    public function test_pax_caps_come_from_config(): void
    {
        $this->assertSame(7, BookingType::Transient->paxCap());
        $this->assertSame(12, BookingType::Room->paxCap());
        $this->assertNull(BookingType::Pool->paxCap());
        $this->assertNull(BookingType::VenueHall->paxCap());
        $this->assertSame(5, config('zk.notice_days'));
        $this->assertSame(24, config('zk.hold_hours'));
    }

    public function test_two_blocking_reservations_cannot_share_a_date(): void
    {
        $date = now()->addDays(20)->toDateString();
        Reservation::factory()->create(['reservation_date' => $date, 'blocking_date' => $date]);

        $this->expectException(QueryException::class);
        Reservation::factory()->create(['reservation_date' => $date, 'blocking_date' => $date]);
    }

    public function test_released_reservations_do_not_block_and_the_date_can_be_rebooked(): void
    {
        $date = now()->addDays(20)->toDateString();

        Reservation::factory()->released(ReservationStatus::Expired)->create(['reservation_date' => $date]);
        Reservation::factory()->released(ReservationStatus::Cancelled)->create(['reservation_date' => $date]);
        $live = Reservation::factory()->create(['reservation_date' => $date, 'blocking_date' => $date]);

        $this->assertSame(3, Reservation::whereDate('reservation_date', $date)->count());
        $this->assertTrue($live->status->blocksDate());
    }

    public function test_walk_in_has_no_account_and_uses_typed_name(): void
    {
        $walkIn = Reservation::factory()->create([
            'user_id' => null,
            'is_walk_in' => true,
            'guest_name' => 'Juan Dela Cruz',
            'guest_contact' => '09171234567',
            'status' => ReservationStatus::Confirmed,
            'hold_expires_at' => null,
        ]);

        $this->assertNull($walkIn->user);
        $this->assertSame('Juan Dela Cruz', $walkIn->bookerName());
    }

    public function test_guest_reservation_uses_account_name_and_casts_work(): void
    {
        $guest = User::factory()->create(['name' => 'Maria Santos']);
        $r = Reservation::factory()->for($guest)->create([
            'change_request' => ChangeRequest::Reschedule,
            'requested_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->assertSame('Maria Santos', $r->bookerName());
        $this->assertSame(ChangeRequest::Reschedule, $r->fresh()->change_request);
        $this->assertSame(ReservationStatus::SlotHeld, $r->fresh()->status);
        $this->assertFalse($r->fresh()->downpayment_forfeited);
        $this->assertTrue($guest->reservations->contains($r));
    }

    public function test_latest_payment_proof_is_the_current_one(): void
    {
        $r = Reservation::factory()->create();
        PaymentProof::create(['reservation_id' => $r->id, 'path' => 'proofs/a.jpg', 'status' => 'flagged']);
        $second = PaymentProof::create(['reservation_id' => $r->id, 'path' => 'proofs/b.jpg', 'status' => 'for_review']);

        $this->assertSame(2, $r->paymentProofs()->count());
        $this->assertSame($second->id, $r->latestPaymentProof->id);
    }

    public function test_notifications_belong_to_a_user_and_start_unread(): void
    {
        $user = User::factory()->create();
        $n = UserNotification::create(['user_id' => $user->id, 'type' => 'slot_held', 'message' => 'Slot Held']);

        $this->assertNull($n->fresh()->read_at);
        $this->assertNotNull($n->fresh()->created_at);
        $this->assertSame(1, $user->appNotifications()->count());
    }

    public function test_activity_log_rows_cannot_be_changed_or_deleted(): void
    {
        $log = ActivityLog::create([
            'category' => LogCategory::Reservation,
            'event' => 'submitted',
            'actor_type' => ActorType::Guest,
            'description' => 'Reservation submitted',
        ]);

        $log->description = 'tampered';
        $this->assertFalse($log->save());
        $this->assertFalse($log->delete());

        $fresh = $log->fresh();
        $this->assertSame('Reservation submitted', $fresh->description);
        $this->assertSame(ActorType::Guest, $fresh->actor_type);
    }

    public function test_seeder_creates_one_admin_from_config_and_is_repeatable(): void
    {
        config(['zk.admin.email' => 'admin@example.test', 'zk.admin.password' => 'secret-pass-123']);

        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $admins = User::where('role', 'admin')->get();
        $this->assertCount(1, $admins);
        $this->assertTrue($admins->first()->isAdmin());
        $this->assertTrue($admins->first()->is_active);
        $this->assertNotSame('secret-pass-123', $admins->first()->password); // hashed
    }

    public function test_seeder_refuses_to_run_without_admin_credentials(): void
    {
        config(['zk.admin.email' => null, 'zk.admin.password' => null]);

        $this->expectException(RuntimeException::class);
        $this->seed(AdminSeeder::class);
    }

    public function test_new_users_default_to_active_guests(): void
    {
        $user = User::create(['name' => 'A', 'email' => 'a@example.test', 'password' => 'x']);

        $this->assertTrue($user->fresh()->isGuest());
        $this->assertTrue($user->fresh()->is_active);
    }
}
