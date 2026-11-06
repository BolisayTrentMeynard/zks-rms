<?php

namespace Tests\Feature;

use App\Enums\ChangeRequest;
use App\Enums\ProofStatus;
use App\Enums\ReservationStatus;
use App\Models\PaymentProof;
use App\Models\Reservation;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('zk.proof_disk'));
        $this->admin = User::factory()->admin()->create();
    }

    private function service(): ReservationService
    {
        return app(ReservationService::class);
    }

    private function inStatus(ReservationStatus $status, array $over = []): Reservation
    {
        return Reservation::factory()->create(['status' => $status, 'hold_expires_at' => null] + $over);
    }

    private function verifying(): Reservation
    {
        $r = $this->inStatus(ReservationStatus::VerifyingPayment);
        PaymentProof::create([
            'reservation_id' => $r->id,
            'path' => UploadedFile::fake()->image('g.jpg')->store('payment-proofs', config('zk.proof_disk')),
            'status' => ProofStatus::ForReview,
        ]);

        return $r;
    }

    private function types(User $user): array
    {
        return UserNotification::where('user_id', $user->id)->pluck('type')->all();
    }

    public function test_a_proof_upload_notifies_the_admin_only(): void
    {
        $r = Reservation::factory()->create();

        $this->assertTrue($this->service()->submitProof($r, UploadedFile::fake()->image('g.jpg'), $r->user));

        $this->assertSame(['proof_uploaded'], $this->types($this->admin));
        $this->assertSame([], $this->types($r->user)); // not for the guest's own action
    }

    public function test_confirming_and_flagging_notify_the_guest_not_the_admin(): void
    {
        $a = $this->verifying();
        $this->service()->confirmPayment($a, $this->admin);
        $this->assertSame(['confirmed'], $this->types($a->user));

        $b = $this->verifying();
        $this->service()->flagProof($b, $this->admin);
        $this->assertSame(['action_needed'], $this->types($b->user));

        $this->assertSame([], $this->types($this->admin)); // no "confirmed" notification for the Admin
    }

    public function test_requests_notify_the_admin_and_decisions_notify_the_guest(): void
    {
        $r = $this->inStatus(ReservationStatus::Confirmed, ['reservation_date' => now()->addDays(600)->toDateString(), 'blocking_date' => now()->addDays(600)->toDateString()]);

        $this->assertNull($this->service()->requestCancel($r, $r->user));
        $this->assertSame(['cancel_requested'], $this->types($this->admin));

        $this->assertNull($this->service()->approveChange($r, $this->admin));
        $this->assertSame(['request_approved'], $this->types($r->user));

        $r2 = $this->inStatus(ReservationStatus::Confirmed, ['reservation_date' => now()->addDays(610)->toDateString(), 'blocking_date' => now()->addDays(610)->toDateString()]);
        $this->assertNull($this->service()->requestReschedule($r2, $r2->user, now()->addDays(700)->toDateString()));
        $this->assertContains('reschedule_requested', $this->types($this->admin));

        $this->assertNull($this->service()->approveChange($r2, $this->admin));
        $this->assertSame(['rescheduled'], $this->types($r2->user));

        $r3 = $this->inStatus(ReservationStatus::Confirmed, ['change_request' => ChangeRequest::Cancel, 'change_requested_at' => now()]);
        $this->assertTrue($this->service()->declineChange($r3, $this->admin));
        $this->assertSame(['request_declined'], $this->types($r3->user));
    }

    public function test_admin_cancel_notifies_the_guest_and_a_walk_in_notifies_nobody(): void
    {
        $r = $this->inStatus(ReservationStatus::Confirmed);
        $this->assertTrue($this->service()->adminCancel($r, $this->admin));
        $this->assertSame(['cancelled'], $this->types($r->user));

        $walkIn = Reservation::factory()->create([
            'user_id' => null, 'is_walk_in' => true, 'guest_name' => 'Pedro', 'guest_contact' => '0917',
            'status' => ReservationStatus::Confirmed, 'hold_expires_at' => null,
        ]);
        $this->assertTrue($this->service()->adminCancel($walkIn, $this->admin));
        $this->assertSame(1, UserNotification::where('type', 'cancelled')->count());
    }

    public function test_a_guest_cancelling_their_own_held_reservation_sends_nothing(): void
    {
        $r = Reservation::factory()->create();
        $this->assertTrue($this->service()->cancelHeld($r, $r->user));

        $this->assertSame(0, UserNotification::count());
    }

    public function test_an_expired_hold_notifies_both(): void
    {
        $r = Reservation::factory()->create(['hold_expires_at' => now()->subMinute()]);

        $this->artisan('reservations:expire-holds')->assertSuccessful();

        $this->assertSame(['expired'], $this->types($r->user));
        $this->assertSame(['hold_expired'], $this->types($this->admin));
    }

    public function test_the_two_hour_nudge_is_sent_once_and_only_when_due(): void
    {
        $due = Reservation::factory()->create(['hold_expires_at' => now()->addMinutes(90)]);
        $early = Reservation::factory()->create(['hold_expires_at' => now()->addHours(10)]);
        $ended = Reservation::factory()->create(['hold_expires_at' => now()->subMinute()]);
        $verifying = $this->inStatus(ReservationStatus::VerifyingPayment);

        $this->artisan('reservations:send-hold-reminders')->assertSuccessful();
        $this->artisan('reservations:send-hold-reminders')->assertSuccessful(); // second run sends nothing

        $this->assertSame(['hold_reminder'], $this->types($due->user));
        $this->assertSame(['hold_near_expiry'], $this->types($this->admin));
        $this->assertNotNull($due->fresh()->hold_reminder_sent_at);
        foreach ([$early, $ended, $verifying] as $other) {
            $this->assertSame([], $this->types($other->user));
        }
    }

    public function test_the_page_shows_only_my_notifications_newest_first_with_an_empty_state(): void
    {
        $guest = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($guest)->get(route('notifications.index'))->assertOk()->assertSee('No notifications yet.');

        $old = UserNotification::create(['user_id' => $guest->id, 'type' => 'confirmed', 'message' => 'OLD-MESSAGE']);
        $new = UserNotification::create(['user_id' => $guest->id, 'type' => 'expired', 'message' => 'NEW-MESSAGE']);
        UserNotification::create(['user_id' => $other->id, 'type' => 'expired', 'message' => 'OTHERS-MESSAGE']);
        \DB::table('user_notifications')->where('id', $old->id)->update(['created_at' => now()->subDay()]);

        $this->get(route('notifications.index'))
            ->assertSeeInOrder(['NEW-MESSAGE', 'OLD-MESSAGE'])
            ->assertDontSee('OTHERS-MESSAGE')
            ->assertDontSee('No notifications yet.');
    }

    public function test_the_bell_shows_the_unread_count(): void
    {
        $guest = User::factory()->create();
        UserNotification::create(['user_id' => $guest->id, 'type' => 'confirmed', 'message' => 'One']);
        UserNotification::create(['user_id' => $guest->id, 'type' => 'expired', 'message' => 'Two']);
        UserNotification::create(['user_id' => $guest->id, 'type' => 'expired', 'message' => 'Read one', 'read_at' => now()]);

        $this->actingAs($guest)->get(route('availability'))->assertSee('2 unread');
        $this->get(route('availability'))->assertSee('Notifications');
    }

    public function test_clicking_marks_it_read_and_opens_the_reservation_for_each_role(): void
    {
        $r = Reservation::factory()->create();
        $forGuest = UserNotification::create(['user_id' => $r->user_id, 'reservation_id' => $r->id, 'type' => 'confirmed', 'message' => 'G']);
        $forAdmin = UserNotification::create(['user_id' => $this->admin->id, 'reservation_id' => $r->id, 'type' => 'proof_uploaded', 'message' => 'A']);

        $this->actingAs($r->user)->get(route('notifications.open', $forGuest))->assertRedirect(route('reservations.show', $r));
        $this->assertNotNull($forGuest->fresh()->read_at);

        $this->actingAs($this->admin)->get(route('notifications.open', $forAdmin))->assertRedirect(route('admin.reservations.show', $r));
        $this->assertNotNull($forAdmin->fresh()->read_at);
    }

    public function test_nobody_can_open_or_mark_someone_elses_notification(): void
    {
        $r = Reservation::factory()->create();
        $mine = UserNotification::create(['user_id' => $r->user_id, 'reservation_id' => $r->id, 'type' => 'confirmed', 'message' => 'Mine']);

        $this->actingAs(User::factory()->create())->get(route('notifications.open', $mine))->assertNotFound();
        $this->assertNull($mine->fresh()->read_at);

        $this->post(route('notifications.read-all'));
        $this->assertNull($mine->fresh()->read_at); // only the caller's own rows change
    }

    public function test_mark_all_as_read(): void
    {
        $guest = User::factory()->create();
        UserNotification::create(['user_id' => $guest->id, 'type' => 'confirmed', 'message' => 'One']);
        UserNotification::create(['user_id' => $guest->id, 'type' => 'expired', 'message' => 'Two']);

        $this->actingAs($guest)->post(route('notifications.read-all'))->assertRedirect(route('notifications.index'));

        $this->assertSame(0, $guest->unreadNotificationCount());
    }

    public function test_visitors_cannot_open_the_page(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }
}