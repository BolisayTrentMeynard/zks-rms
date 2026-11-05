<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\LogCategory;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\User;

/**
 * One place that writes to the read-only activity log.
 * account() for account events, reservation() for reservation events.
 */
class ActivityLogger
{
    /**
     * Record an account event (registered, login, logout, profile_edited, ...).
     * The actor is the user themself (Guest or Admin) unless another actor is given.
     */
    public static function account(string $event, User $user, string $description, ?ActorType $actor = null): ActivityLog
    {
        $actor ??= $user->isAdmin() ? ActorType::Admin : ActorType::Guest;

        return ActivityLog::create([
            'category' => LogCategory::Account,
            'event' => $event,
            'actor_type' => $actor,
            'actor_id' => $actor === ActorType::System ? null : $user->id,
            'user_id' => $user->id,
            'description' => $description,
        ]);
    }

    /**
     * Record a reservation event (submitted, proof uploaded, confirmed, ...).
     * $actorUser is the person who did it; leave it null when the actor is the System.
     */
    public static function reservation(string $event, Reservation $reservation, ActorType $actor, string $description, ?User $actorUser = null): ActivityLog
    {
        return ActivityLog::create([
            'category' => LogCategory::Reservation,
            'event' => $event,
            'actor_type' => $actor,
            'actor_id' => $actor === ActorType::System ? null : $actorUser?->id,
            'user_id' => $reservation->user_id,
            'reservation_id' => $reservation->id,
            'description' => $description,
        ]);
    }
}
