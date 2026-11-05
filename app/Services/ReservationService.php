<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\BookingType;
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
 * Rules for creating a reservation. The walk-in form (a later chunk) reuses this.
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
