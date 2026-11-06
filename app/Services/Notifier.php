<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\User;
use App\Models\UserNotification;

/**
 * One place that writes in-app notifications (no SMS, email or push).
 * Call it inside the same transaction as the status change it describes.
 */
class Notifier
{
    /** Tell the guest who owns the reservation. Walk-ins have no account, so nothing is sent. */
    public static function guest(Reservation $reservation, string $type, string $message): void
    {
        if (! $reservation->user_id) {
            return;
        }

        UserNotification::create([
            'user_id' => $reservation->user_id,
            'reservation_id' => $reservation->id,
            'type' => $type,
            'message' => $message,
        ]);
    }

    /** Tell the Admin (every account with the Admin role; in practice the one shared login). */
    public static function admin(Reservation $reservation, string $type, string $message): void
    {
        foreach (User::where('role', UserRole::Admin)->pluck('id') as $adminId) {
            UserNotification::create([
                'user_id' => $adminId,
                'reservation_id' => $reservation->id,
                'type' => $type,
                'message' => $message,
            ]);
        }
    }
}
