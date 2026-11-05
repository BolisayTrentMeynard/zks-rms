<?php

namespace App\Services;

use App\Enums\ActorType;
use App\Enums\LogCategory;
use App\Models\ActivityLog;
use App\Models\User;

/**
 * One place that writes to the read-only activity log.
 * Chunk 2 uses account(); reservation events are added in later chunks.
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
}
