<?php

namespace Tests\Feature\Admin;

use App\Enums\ActorType;
use App\Enums\ProofStatus;
use App\Enums\ReservationStatus;
use App\Models\ActivityLog;
use App\Models\PaymentProof;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('zk.proof_disk'));
        $this->admin = User::factory()->admin()->create();
    }

    /** A reservation in Verifying Payment with one proof waiting for review. */
    private function verifying(array $over = []): Reservation
    {
        $reservation = Reservation::factory()->create(array_merge(
            ['status' => ReservationStatus::VerifyingPayment, 'hold_expires_at' => null],
            $over,
        ));

        PaymentProof::create([
            'reservation_id' => $reservation->id,
            'path' => UploadedFile::fake()->image('gcash.jpg')->store('payment-proofs', config('zk.proof_disk')),
            'status' => ProofStatus::ForReview,
        ]);

        return $reservation;
    }

    public function test_the_detail_page_shows_the_proof_and_both_buttons(): void
    {
        $reservation = $this->verifying();
        $proof = $reservation->latestPaymentProof;

        $this->actingAs($this->admin)->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee($reservation->user->name)
            ->assertSee(route('admin.proofs.show', $proof), false)
            ->assertSee('Confirm payment')
            ->assertSee('Flag proof');
    }

    public function test_confirming_accepts_the_proof_confirms_the_reservation_and_logs_it(): void
    {
        $reservation = $this->verifying();

        $this->actingAs($this->admin)->post(route('admin.reservations.confirm', $reservation))
            ->assertRedirect(route('admin.reservations.show', $reservation))
            ->assertSessionHas('status');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertNotNull($reservation->blocking_date); // the date stays blocked
        $this->assertSame(ProofStatus::Accepted, $reservation->latestPaymentProof->status);
        $this->assertNotNull($reservation->latestPaymentProof->reviewed_at);

        $log = ActivityLog::where('event', 'confirmed')->firstOrFail();
        $this->assertSame(ActorType::Admin, $log->actor_type);
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame($reservation->id, $log->reservation_id);
        $this->assertSame($reservation->user_id, $log->user_id);
    }

    public function test_flagging_sets_action_needed_keeps_the_date_blocked_and_logs_it(): void
    {
        $reservation = $this->verifying();

        $this->actingAs($this->admin)->post(route('admin.reservations.flag', $reservation))
            ->assertRedirect(route('admin.reservations.show', $reservation))
            ->assertSessionHas('status');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::ActionNeeded, $reservation->status);
        $this->assertNotNull($reservation->blocking_date);
        $this->assertNull($reservation->hold_expires_at); // Action Needed never expires
        $this->assertSame(ProofStatus::Flagged, $reservation->latestPaymentProof->status);

        $log = ActivityLog::where('event', 'flagged')->firstOrFail();
        $this->assertSame(ActorType::Admin, $log->actor_type);
        $this->assertSame($this->admin->id, $log->actor_id);
    }

    public function test_a_flagged_proof_can_be_replaced_and_then_confirmed(): void
    {
        $reservation = $this->verifying();

        $this->actingAs($this->admin)->post(route('admin.reservations.flag', $reservation));
        $this->assertSame(ReservationStatus::ActionNeeded, $reservation->fresh()->status);

        $this->actingAs($reservation->user)
            ->post(route('reservations.proof', $reservation), ['proof' => UploadedFile::fake()->image('clearer.jpg')])
            ->assertSessionHas('status');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::VerifyingPayment, $reservation->status);
        $this->assertSame(2, $reservation->paymentProofs()->count());

        $this->actingAs($this->admin)->post(route('admin.reservations.confirm', $reservation));

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertSame(ProofStatus::Accepted, $reservation->latestPaymentProof->status);
        $this->assertSame(ProofStatus::Flagged, $reservation->paymentProofs()->oldest('id')->first()->status);
    }

    public function test_nothing_can_be_reviewed_in_the_wrong_status(): void
    {
        $cases = [
            ['status' => ReservationStatus::SlotHeld],
            ['status' => ReservationStatus::Confirmed, 'hold_expires_at' => null],
            ['status' => ReservationStatus::ActionNeeded, 'hold_expires_at' => null],
            ['status' => ReservationStatus::Expired, 'hold_expires_at' => null, 'blocking_date' => null],
            ['status' => ReservationStatus::Cancelled, 'hold_expires_at' => null, 'blocking_date' => null],
        ];

        foreach ($cases as $state) {
            $reservation = $this->verifying($state);
            $before = $reservation->status;

            foreach (['admin.reservations.confirm', 'admin.reservations.flag'] as $route) {
                $this->actingAs($this->admin)->post(route($route, $reservation))->assertSessionHas('error');
            }

            $this->assertSame($before, $reservation->fresh()->status);
            $this->assertSame(ProofStatus::ForReview, $reservation->latestPaymentProof->fresh()->status);
        }

        $this->assertSame(0, ActivityLog::whereIn('event', ['confirmed', 'flagged'])->count());
    }

    public function test_a_reservation_without_a_proof_cannot_be_confirmed(): void
    {
        $reservation = Reservation::factory()->create(['status' => ReservationStatus::VerifyingPayment, 'hold_expires_at' => null]);

        $this->actingAs($this->admin)->post(route('admin.reservations.confirm', $reservation))->assertSessionHas('error');

        $this->assertSame(ReservationStatus::VerifyingPayment, $reservation->fresh()->status);
    }

    public function test_reviewing_twice_only_works_once(): void
    {
        $reservation = $this->verifying();
        $this->actingAs($this->admin);

        $this->post(route('admin.reservations.confirm', $reservation))->assertSessionHas('status');
        $this->post(route('admin.reservations.confirm', $reservation))->assertSessionHas('error');
        $this->post(route('admin.reservations.flag', $reservation))->assertSessionHas('error');

        $this->assertSame(ReservationStatus::Confirmed, $reservation->fresh()->status);
        $this->assertSame(1, ActivityLog::where('event', 'confirmed')->count());
        $this->assertSame(0, ActivityLog::where('event', 'flagged')->count());
    }

    public function test_guests_and_visitors_cannot_review(): void
    {
        $reservation = $this->verifying();

        foreach (['admin.reservations.confirm', 'admin.reservations.flag'] as $route) {
            $this->post(route($route, $reservation))->assertRedirect(route('login'));
            $this->actingAs($reservation->user)->post(route($route, $reservation))->assertForbidden();
            auth()->logout();
        }

        $this->assertSame(ReservationStatus::VerifyingPayment, $reservation->fresh()->status);
    }

    public function test_the_admin_can_view_the_proof_image(): void
    {
        $reservation = $this->verifying();
        $proof = $reservation->latestPaymentProof;

        $response = $this->actingAs($this->admin)->get(route('admin.proofs.show', $proof))->assertOk();

        $this->assertStringStartsWith('image/', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_only_the_admin_can_view_a_proof_image_not_the_owner_nor_a_visitor(): void
    {
        $reservation = $this->verifying();
        $url = route('admin.proofs.show', $reservation->latestPaymentProof);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($reservation->user)->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_a_missing_proof_file_is_a_404(): void
    {
        $reservation = $this->verifying();
        $proof = $reservation->latestPaymentProof;
        Storage::disk(config('zk.proof_disk'))->delete($proof->path);

        $this->actingAs($this->admin)->get(route('admin.proofs.show', $proof))->assertNotFound();
    }

    public function test_the_page_says_what_it_is_waiting_for_in_each_status(): void
    {
        $this->actingAs($this->admin);

        $held = Reservation::factory()->create();
        $this->get(route('admin.reservations.show', $held))
            ->assertSee('No payment proof has been sent yet.')
            ->assertDontSee('Confirm payment');

        $flagged = $this->verifying(['status' => ReservationStatus::ActionNeeded]);
        $this->get(route('admin.reservations.show', $flagged))
            ->assertSee('Waiting for the guest to upload a clearer screenshot')
            ->assertDontSee('Confirm payment');

        $confirmed = $this->verifying(['status' => ReservationStatus::Confirmed]);
        $this->get(route('admin.reservations.show', $confirmed))->assertDontSee('Flag proof');
    }

    public function test_a_walk_in_shows_its_typed_name_and_contact(): void
    {
        $walkIn = Reservation::factory()->create([
            'user_id' => null,
            'is_walk_in' => true,
            'guest_name' => 'Walter Walkin',
            'guest_contact' => '09171234567',
            'status' => ReservationStatus::Confirmed,
            'hold_expires_at' => null,
        ]);

        $this->actingAs($this->admin)->get(route('admin.reservations.show', $walkIn))
            ->assertOk()
            ->assertSee('Walter Walkin')
            ->assertSee('09171234567');
    }
}
