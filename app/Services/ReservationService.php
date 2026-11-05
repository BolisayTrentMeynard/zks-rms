<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\BookingType;
use App\Enums\Duration;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

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
}
