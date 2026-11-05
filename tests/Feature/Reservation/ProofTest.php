<?php

namespace Tests\Feature\Reservation;

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

class ProofTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('zk.proof_disk'));
    }

    private function image(string $name = 'gcash.jpg', int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->image($name)->size($kb);
    }

    private function held(array $over = []): Reservation
    {
        return Reservation::factory()->create($over);
    }

    public function test_guest_uploads_a_proof_and_the_reservation_moves_to_verifying_payment(): void
    {
        $reservation = $this->held();

        $this->actingAs($reservation->user)
            ->post(route('reservations.proof', $reservation), ['proof' => $this->image()])
            ->assertRedirect(route('reservations.show', $reservation))
            ->assertSessionHas('status');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::VerifyingPayment, $reservation->status);
        $this->assertNull($reservation->hold_expires_at);
        $this->assertNotNull($reservation->blocking_date); // the date stays blocked

        $proof = $reservation->latestPaymentProof;
        $this->assertSame(ProofStatus::ForReview, $proof->status);
        $this->assertStringStartsWith('payment-proofs/', $proof->path);
        Storage::disk(config('zk.proof_disk'))->assertExists($proof->path);
    }

    public function test_the_proof_is_never_stored_on_the_public_disk(): void
    {
        $reservation = $this->held();
        $this->actingAs($reservation->user)->post(route('reservations.proof', $reservation), ['proof' => $this->image()]);

        $path = $reservation->latestPaymentProof->path;
        $this->assertNotSame('public', config('zk.proof_disk'));
        $this->assertFileDoesNotExist(public_path($path));
        $this->assertFileDoesNotExist(storage_path('app/public/'.$path));
    }

    public function test_uploading_is_logged_with_the_guest_as_actor(): void
    {
        $reservation = $this->held();
        $this->actingAs($reservation->user)->post(route('reservations.proof', $reservation), ['proof' => $this->image()]);

        $log = ActivityLog::where('event', 'proof_uploaded')->firstOrFail();
        $this->assertSame(ActorType::Guest, $log->actor_type);
        $this->assertSame($reservation->user_id, $log->actor_id);
        $this->assertSame($reservation->id, $log->reservation_id);
    }

    public function test_bad_files_are_rejected_and_nothing_changes(): void
    {
        $reservation = $this->held();
        $this->actingAs($reservation->user);
        $url = route('reservations.proof', $reservation);

        $this->post($url, [])->assertSessionHasErrors('proof');
        $this->post($url, ['proof' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')])->assertSessionHasErrors('proof');
        $this->post($url, ['proof' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])->assertSessionHasErrors('proof');
        $this->post($url, ['proof' => $this->image('big.jpg', config('zk.proof_max_kb') + 100)])->assertSessionHasErrors('proof');

        $this->assertSame(0, PaymentProof::count());
        $this->assertSame(ReservationStatus::SlotHeld, $reservation->fresh()->status);
    }

    public function test_only_the_owner_can_upload(): void
    {
        $reservation = $this->held();
        $url = route('reservations.proof', $reservation);

        $this->post($url, ['proof' => $this->image()])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post($url, ['proof' => $this->image()])->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post($url, ['proof' => $this->image()])->assertForbidden();
        $this->assertSame(0, PaymentProof::count());
    }

    public function test_a_proof_cannot_be_sent_in_the_wrong_status(): void
    {
        $cases = [
            ['status' => ReservationStatus::VerifyingPayment, 'hold_expires_at' => null],
            ['status' => ReservationStatus::Confirmed, 'hold_expires_at' => null],
            ['status' => ReservationStatus::Expired, 'hold_expires_at' => null, 'blocking_date' => null],
            ['status' => ReservationStatus::Cancelled, 'hold_expires_at' => null, 'blocking_date' => null],
        ];

        foreach ($cases as $state) {
            $reservation = $this->held($state);

            $this->actingAs($reservation->user)
                ->post(route('reservations.proof', $reservation), ['proof' => $this->image()])
                ->assertRedirect(route('reservations.show', $reservation))
                ->assertSessionHas('error');
        }

        $this->assertSame(0, PaymentProof::count());
    }

    public function test_a_proof_is_refused_after_the_hold_ended_even_if_the_expiry_job_has_not_run(): void
    {
        $reservation = $this->held(['hold_expires_at' => now()->subMinute()]);

        $this->actingAs($reservation->user)
            ->post(route('reservations.proof', $reservation), ['proof' => $this->image()])
            ->assertSessionHas('error');

        $this->assertSame(0, PaymentProof::count());
        $this->assertSame(ReservationStatus::SlotHeld, $reservation->fresh()->status);
    }

    public function test_action_needed_allows_a_new_proof_and_keeps_the_old_one(): void
    {
        $reservation = $this->held(['status' => ReservationStatus::ActionNeeded, 'hold_expires_at' => null]);
        $old = PaymentProof::create(['reservation_id' => $reservation->id, 'path' => 'payment-proofs/old.jpg', 'status' => ProofStatus::Flagged]);

        $this->actingAs($reservation->user)
            ->post(route('reservations.proof', $reservation), ['proof' => $this->image('clearer.jpg')])
            ->assertSessionHas('status');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::VerifyingPayment, $reservation->status);
        $this->assertSame(2, $reservation->paymentProofs()->count());
        $this->assertNotSame($old->id, $reservation->latestPaymentProof->id);
        $this->assertSame(ProofStatus::ForReview, $reservation->latestPaymentProof->status);
        $this->assertStringContainsString('re-uploaded', ActivityLog::where('event', 'proof_uploaded')->firstOrFail()->description);
    }

    public function test_the_reservation_page_shows_the_right_thing_for_each_status(): void
    {
        $slot = $this->held();
        $this->actingAs($slot->user)->get(route('reservations.show', $slot))
            ->assertSee('Send payment proof')->assertSee('Time left to hold this date');

        $verifying = $this->held(['status' => ReservationStatus::VerifyingPayment, 'hold_expires_at' => null]);
        $this->actingAs($verifying->user)->get(route('reservations.show', $verifying))
            ->assertSee('checking it')->assertDontSee('Send payment proof');

        $flagged = $this->held(['status' => ReservationStatus::ActionNeeded, 'hold_expires_at' => null]);
        $this->actingAs($flagged->user)->get(route('reservations.show', $flagged))
            ->assertSee('We could not accept your payment proof')->assertSee('Send payment proof');

        $confirmed = $this->held(['status' => ReservationStatus::Confirmed, 'hold_expires_at' => null]);
        $this->actingAs($confirmed->user)->get(route('reservations.show', $confirmed))
            ->assertSee('Your reservation is confirmed')->assertDontSee('Send payment proof');

        $ended = $this->held(['hold_expires_at' => now()->subMinute()]);
        $this->actingAs($ended->user)->get(route('reservations.show', $ended))
            ->assertSee('The hold has ended')->assertDontSee('Send payment proof');
    }
}
