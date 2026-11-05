<?php

namespace Database\Factories;

use App\Enums\BookingType;
use App\Enums\Duration;
use App\Enums\ReservationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reservation>
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        $date = now()->addDays(fake()->unique()->numberBetween(10, 400))->toDateString();

        return [
            'user_id' => User::factory(),
            'is_walk_in' => false,
            'reservation_date' => $date,
            'duration' => Duration::WholeDay,
            'booking_type' => BookingType::Pool,
            'pax' => 5,
            'occasion' => 'Birthday',
            'status' => ReservationStatus::SlotHeld,
            'hold_expires_at' => now()->addHours(config('zk.hold_hours')),
            'blocking_date' => $date,
        ];
    }

    /** Status that does not block the date (Expired / Cancelled): blocking_date is cleared. */
    public function released(ReservationStatus $status = ReservationStatus::Expired): static
    {
        return $this->state(fn () => ['status' => $status, 'blocking_date' => null, 'hold_expires_at' => null]);
    }
}
