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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
