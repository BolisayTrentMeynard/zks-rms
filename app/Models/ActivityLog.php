<?php

namespace App\Models;

use App\Enums\ActorType;
use App\Enums\LogCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-only audit trail. Rows can be created but never changed or deleted.
 */
class ActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'category',
        'event',
        'actor_type',
        'actor_id',
        'user_id',
        'reservation_id',
        'description',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    protected function casts(): array
    {
        return [
            'category' => LogCategory::class,
            'actor_type' => ActorType::class,
            'created_at' => 'datetime',
        ];
    }

    /** Plain-words name for an event, used by the Activity Log screen. */
    public function eventLabel(): string
    {
        return match ($this->event) {
            'submitted' => 'Reservation submitted',
            'proof_uploaded' => 'Proof uploaded',
            'confirmed' => 'Payment confirmed',
            'flagged' => 'Proof flagged (Action Needed)',
            'expired' => 'Hold expired',
            'cancelled' => 'Reservation cancelled',
            'cancel_requested' => 'Cancellation requested',
            'reschedule_requested' => 'Reschedule requested',
            'request_declined' => 'Request declined',
            'rescheduled' => 'Reservation rescheduled',
            'walk_in_entered' => 'Walk-in entered',
            'registered' => 'Guest registered',
            'login' => 'Logged in',
            'logout' => 'Logged out',
            'profile_edited' => 'Profile edited',
            'deactivated' => 'Account deactivated',
            'reactivated' => 'Account reactivated',
            default => ucfirst(str_replace('_', ' ', $this->event)),
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
