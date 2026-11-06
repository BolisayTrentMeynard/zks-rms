<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\BookingType;
use App\Enums\ChangeRequest;
use App\Enums\Duration;
use App\Enums\ProofStatus;
use App\Enums\ReservationStatus;
use App\Models\PaymentProof;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Rules for creating a reservation. Guests use hold(); the Admin's walk-in form uses walkIn().
 */
class ReservationService
{
    /** The first date a guest can book online (today + min_advance_days). */
    public static function earliestDate(): Carbon
    {
        return today()->addDays((int) config('zk.min_advance_days'));
    }

    /** True while any reservation blocks this date for the whole resort. */
    public static function isDateBlocked(Carbon|string $date): bool
    {
        return Reservation::whereDate('blocking_date', Carbon::parse($date)->toDateString())->exists();
    }

    /**
     * Put a Slot Held reservation on a date for a guest account.
     * Returns null when the date is taken (including when someone else wins a race for it).
     *
     * @param  array{reservation_date: string, duration: string, booking_type: string, pax: int|string, occasion: string}  $data
     */
    public function hold(User $user, array $data): ?Reservation
    {
        $date = Carbon::parse($data['reservation_date'])->toDateString();

        if (self::isDateBlocked($date)) {
            return null;
        }

        try {
            return DB::transaction(function () use ($user, $data, $date) {
                $reservation = Reservation::create([
                    'user_id' => $user->id,
                    'is_walk_in' => false,
                    'reservation_date' => $date,
                    'duration' => Duration::from($data['duration']),
                    'booking_type' => BookingType::from($data['booking_type']),
                    'pax' => (int) $data['pax'],
                    'occasion' => $data['occasion'],
                    'status' => ReservationStatus::SlotHeld,
                    'hold_expires_at' => now()->addHours((int) config('zk.hold_hours')),
                    'blocking_date' => $date,
                ]);

                ActivityLogger::reservation(
                    'submitted',
                    $reservation,
                    ActorType::Guest,
                    sprintf(
                        'Reservation submitted for %s (%s, %s, %d pax)',
                        Carbon::parse($date)->format('M j, Y'),
                        $reservation->booking_type->label(),
                        $reservation->duration->label(),
                        $reservation->pax,
                    ),
                    $user,
                );

                return $reservation;
            });
        } catch (UniqueConstraintViolationException) {
            // The UNIQUE blocking_date index caught a second booking on the same date.
            return null;
        }
    }

    /**
     * Admin enters a walk-in: no account, no hold and no payment proof, Confirmed at once.
     * Returns null when the date is taken (including when someone else wins a race for it).
     *
     * @param  array{guest_name: string, guest_contact: string, reservation_date: string, duration: string, booking_type: string, pax: int|string, occasion: string}  $data
     */
    public function walkIn(User $admin, array $data): ?Reservation
    {
        $date = Carbon::parse($data['reservation_date'])->toDateString();

        if (self::isDateBlocked($date)) {
            return null;
        }

        try {
            return DB::transaction(function () use ($admin, $data, $date) {
                $reservation = Reservation::create([
                    'user_id' => null,
                    'is_walk_in' => true,
                    'guest_name' => $data['guest_name'],
                    'guest_contact' => $data['guest_contact'],
                    'reservation_date' => $date,
                    'duration' => Duration::from($data['duration']),
                    'booking_type' => BookingType::from($data['booking_type']),
                    'pax' => (int) $data['pax'],
                    'occasion' => $data['occasion'],
                    'status' => ReservationStatus::Confirmed,
                    'hold_expires_at' => null,
                    'blocking_date' => $date,
                ]);

                ActivityLogger::reservation(
                    'walk_in_entered',
                    $reservation,
                    ActorType::Admin,
                    sprintf(
                        'Walk-in entered for %s: %s (%s, %s, %d pax)',
                        Carbon::parse($date)->format('M j, Y'),
                        $reservation->guest_name,
                        $reservation->booking_type->label(),
                        $reservation->duration->label(),
                        $reservation->pax,
                    ),
                    $admin,
                );

                return $reservation;
            });
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /**
     * Save a payment proof image and move the reservation to Verifying Payment.
     * Returns false when a proof is not allowed right now (wrong status, or the hold has ended
     * even if the expiry job has not run yet). The image goes on the private disk only.
     */
    public function submitProof(Reservation $reservation, UploadedFile $file, User $guest): bool
    {
        $path = null;

        try {
            return DB::transaction(function () use ($reservation, $file, $guest, &$path) {
                $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

                if (! $locked->canUploadProof()) {
                    return false;
                }

                $isResubmission = $locked->status === ReservationStatus::ActionNeeded;
                $path = $file->store('payment-proofs', config('zk.proof_disk'));

                PaymentProof::create([
                    'reservation_id' => $locked->id,
                    'path' => $path,
                    'status' => ProofStatus::ForReview,
                ]);

                // The date stays blocked. The 24-hour clock only applies to Slot Held.
                $locked->update([
                    'status' => ReservationStatus::VerifyingPayment,
                    'hold_expires_at' => null,
                ]);

                ActivityLogger::reservation(
                    'proof_uploaded',
                    $locked,
                    ActorType::Guest,
                    ($isResubmission ? 'Payment proof re-uploaded for ' : 'Payment proof uploaded for ')
                        .$locked->reservation_date->format('M j, Y'),
                    $guest,
                );

                return true;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk(config('zk.proof_disk'))->delete($path); // do not leave an orphan file
            }

            throw $e;
        }
    }

    /**
     * Admin accepts the current payment proof: Verifying Payment becomes Confirmed.
     * Returns false when there is nothing to review (wrong status, no proof, or already reviewed).
     */
    public function confirmPayment(Reservation $reservation, User $admin): bool
    {
        return $this->reviewProof($reservation, $admin, ProofStatus::Accepted);
    }

    /**
     * Admin flags the current payment proof: Verifying Payment becomes Action Needed.
     * The date stays blocked and the guest uploads a new screenshot. No reason or note is stored.
     */
    public function flagProof(Reservation $reservation, User $admin): bool
    {
        return $this->reviewProof($reservation, $admin, ProofStatus::Flagged);
    }

    private function reviewProof(Reservation $reservation, User $admin, ProofStatus $result): bool
    {
        return DB::transaction(function () use ($reservation, $admin, $result) {
            // Lock the row so a double click or two Admin tabs cannot review the same proof twice.
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ReservationStatus::VerifyingPayment) {
                return false;
            }

            $proof = $locked->paymentProofs()->latest('id')->first();

            if (! $proof || $proof->status !== ProofStatus::ForReview) {
                return false;
            }

            $accepted = $result === ProofStatus::Accepted;
            $date = $locked->reservation_date->format('M j, Y');

            $proof->update(['status' => $result, 'reviewed_at' => now()]);
            $locked->update(['status' => $accepted ? ReservationStatus::Confirmed : ReservationStatus::ActionNeeded]);

            ActivityLogger::reservation(
                $accepted ? 'confirmed' : 'flagged',
                $locked,
                ActorType::Admin,
                $accepted
                    ? 'Payment confirmed for '.$date
                    : 'Payment proof flagged for '.$date.'. The guest was asked for a clearer screenshot.',
                $admin,
            );

            return true;
        });
    }

    /**
     * A guest cancels their own Slot Held reservation. The date opens again at once.
     * Returns false when it is not allowed (not the owner, wrong status, or the hold already ended).
     */
    public function cancelHeld(Reservation $reservation, User $guest): bool
    {
        return DB::transaction(function () use ($reservation, $guest) {
            // Lock the row so a double click or the expiry job cannot act on it twice.
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($locked->user_id !== $guest->id || ! $locked->canSelfCancel()) {
                return false;
            }

            $locked->update([
                'status' => ReservationStatus::Cancelled,
                'blocking_date' => null,
                'hold_expires_at' => null,
            ]);

            ActivityLogger::reservation(
                'cancelled',
                $locked,
                ActorType::Guest,
                'Guest cancelled the held reservation for '.$locked->reservation_date->format('M j, Y').'. The date is open again.',
                $guest,
            );

            return true;
        });
    }

    /** The message a guest sees when a request falls inside the notice window. */
    private static function windowMessage(): string
    {
        return 'Cancellations and reschedules must be requested at least '.(int) config('zk.notice_days')
            .' days before the reserved date. Please contact the resort.';
    }

    /**
     * Guest asks to cancel (Verifying Payment, Action Needed or Confirmed).
     * Returns null when the request was saved, otherwise the message to show the guest.
     */
    public function requestCancel(Reservation $reservation, User $guest): ?string
    {
        return DB::transaction(function () use ($reservation, $guest) {
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($locked->user_id !== $guest->id || ! $locked->canRequestChange()) {
                return 'A cancellation cannot be requested for this reservation right now.';
            }

            if ($locked->withinNoticeWindow()) {
                return self::windowMessage();
            }

            $locked->update([
                'change_request' => ChangeRequest::Cancel,
                'requested_date' => null,
                'change_requested_at' => now(),
            ]);

            ActivityLogger::reservation(
                'cancel_requested',
                $locked,
                ActorType::Guest,
                'Guest requested to cancel the reservation for '.$locked->reservation_date->format('M j, Y'),
                $guest,
            );

            return null;
        });
    }

    /**
     * Guest asks to move a Confirmed reservation to another free date.
     * The new date is not blocked by the request; the old date stays blocked until the Admin approves.
     * Returns null when the request was saved, otherwise the message to show the guest.
     */
    public function requestReschedule(Reservation $reservation, User $guest, string $newDate): ?string
    {
        return DB::transaction(function () use ($reservation, $guest, $newDate) {
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($locked->user_id !== $guest->id || ! $locked->canRequestReschedule()) {
                return 'A reschedule can only be requested for a Confirmed reservation with no other request waiting.';
            }

            if ($locked->withinNoticeWindow()) {
                return self::windowMessage();
            }

            $date = Carbon::parse($newDate)->startOfDay();

            if ($date->lt(self::earliestDate())) {
                return 'Please pick a date from '.self::earliestDate()->format('M j, Y').' onward.';
            }

            if ($date->isSameDay($locked->reservation_date)) {
                return 'That is already the date of this reservation. Please pick a different date.';
            }

            if (self::isDateBlocked($date)) {
                return 'That date is not available. Please pick an open date from the calendar.';
            }

            $locked->update([
                'change_request' => ChangeRequest::Reschedule,
                'requested_date' => $date->toDateString(),
                'change_requested_at' => now(),
            ]);

            ActivityLogger::reservation(
                'reschedule_requested',
                $locked,
                ActorType::Guest,
                sprintf('Guest requested to move the reservation from %s to %s', $locked->reservation_date->format('M j, Y'), $date->format('M j, Y')),
                $guest,
            );

            return null;
        });
    }

    /**
     * Admin approves the pending request.
     * Cancel: the reservation is Cancelled and the date is released.
     * Reschedule: the reservation moves to the new date (which must still be free) and the old date is released.
     * Returns null when done, otherwise the message to show the Admin (nothing changes then).
     */
    public function approveChange(Reservation $reservation, User $admin): ?string
    {
        $unavailable = 'The requested date is no longer available. You can decline the request.';

        try {
            return DB::transaction(function () use ($reservation, $admin, $unavailable) {
                $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

                if (! $locked->change_request) {
                    return 'There is no request waiting on this reservation.';
                }

                $clear = ['change_request' => null, 'requested_date' => null, 'change_requested_at' => null];
                $oldDate = $locked->reservation_date->format('M j, Y');

                if ($locked->change_request === ChangeRequest::Cancel) {
                    $locked->update($clear + [
                        'status' => ReservationStatus::Cancelled,
                        'blocking_date' => null,
                        'hold_expires_at' => null,
                    ]);

                    ActivityLogger::reservation(
                        'cancelled',
                        $locked,
                        ActorType::Admin,
                        'Admin approved the cancellation of the reservation for '.$oldDate.'. The date is open again.',
                        $admin,
                    );

                    return null;
                }

                $new = $locked->requested_date;

                if (! $new || $new->lt(self::earliestDate()) || self::isDateBlocked($new)) {
                    return $unavailable;
                }

                $locked->update($clear + [
                    'reservation_date' => $new->toDateString(),
                    'blocking_date' => $new->toDateString(),
                ]);

                ActivityLogger::reservation(
                    'rescheduled',
                    $locked,
                    ActorType::Admin,
                    sprintf('Admin approved moving the reservation from %s to %s. The old date is open again.', $oldDate, $new->format('M j, Y')),
                    $admin,
                );

                return null;
            });
        } catch (UniqueConstraintViolationException) {
            return $unavailable; // someone took the new date at the same moment
        }
    }

    /** Admin declines the pending request. The reservation stays exactly as it was. */
    public function declineChange(Reservation $reservation, User $admin): bool
    {
        return DB::transaction(function () use ($reservation, $admin) {
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! $locked->change_request) {
                return false;
            }

            $label = $locked->change_request === ChangeRequest::Cancel ? 'cancellation' : 'reschedule';

            $locked->update(['change_request' => null, 'requested_date' => null, 'change_requested_at' => null]);

            ActivityLogger::reservation(
                'request_declined',
                $locked,
                ActorType::Admin,
                'Admin declined the '.$label.' request for '.$locked->reservation_date->format('M j, Y'),
                $admin,
            );

            return true;
        });
    }

    /**
     * Admin cancels any reservation that is still holding its date, at any time (even inside
     * the notice window). The downpayment can be marked forfeited; a Slot Held reservation has
     * no payment, so that flag is ignored there. Returns false when it is already released.
     */
    public function adminCancel(Reservation $reservation, User $admin, bool $forfeitDownpayment = false): bool
    {
        return DB::transaction(function () use ($reservation, $admin, $forfeitDownpayment) {
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->blocksDate()) {
                return false;
            }

            $forfeit = $forfeitDownpayment && $locked->status !== ReservationStatus::SlotHeld;

            $locked->update([
                'status' => ReservationStatus::Cancelled,
                'blocking_date' => null,
                'hold_expires_at' => null,
                'change_request' => null,
                'requested_date' => null,
                'change_requested_at' => null,
                'downpayment_forfeited' => $forfeit,
            ]);

            ActivityLogger::reservation(
                'cancelled',
                $locked,
                ActorType::Admin,
                'Admin cancelled the reservation for '.$locked->reservation_date->format('M j, Y').'. The date is open again.'
                    .($forfeit ? ' Downpayment marked as forfeited.' : ''),
                $admin,
            );

            return true;
        });
    }

    /**
     * Expire every Slot Held reservation whose hold has ended and reopen its date.
     * Only Slot Held is touched. Safe to run repeatedly. Returns how many were expired.
     */
    public function expireDueHolds(): int
    {
        $expired = 0;

        $ids = Reservation::where('status', ReservationStatus::SlotHeld)
            ->where('hold_expires_at', '<=', now())
            ->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$expired) {
                $reservation = Reservation::whereKey($id)->lockForUpdate()->first();

                // Re-check under the lock: a proof may have just been uploaded.
                if (! $reservation
                    || $reservation->status !== ReservationStatus::SlotHeld
                    || ! $reservation->hold_expires_at?->lte(now())) {
                    return;
                }

                $reservation->update([
                    'status' => ReservationStatus::Expired,
                    'blocking_date' => null,
                ]);

                ActivityLogger::reservation(
                    'expired',
                    $reservation,
                    ActorType::System,
                    'Hold expired for '.$reservation->reservation_date->format('M j, Y').'. The date is open again.',
                );

                $expired++;
            });
        }

        return $expired;
    }
}
