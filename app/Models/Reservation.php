<?php

namespace App\Models;

use App\Enums\BookingType;
use App\Enums\ChangeRequest;
use App\Enums\Duration;
use App\Enums\ReservationStatus;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'is_walk_in',
        'guest_name',
        'guest_contact',
        'reservation_date',
        'duration',
        'booking_type',
        'pax',
        'occasion',
        'status',
        'hold_expires_at',
        'hold_reminder_sent_at',
        'blocking_date',
        'change_request',
        'requested_date',
        'change_requested_at',
        'downpayment_forfeited',
    ];

    protected function casts(): array
    {
        return [
            'is_walk_in' => 'boolean',
            'reservation_date' => 'date',
            'duration' => Duration::class,
            'booking_type' => BookingType::class,
            'pax' => 'integer',
            'status' => ReservationStatus::class,
            'hold_expires_at' => 'datetime',
            'hold_reminder_sent_at' => 'datetime',
            'blocking_date' => 'date',
            'change_request' => ChangeRequest::class,
            'requested_date' => 'date',
            'change_requested_at' => 'datetime',
            'downpayment_forfeited' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentProofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    /** The newest proof is the current one. */
    public function latestPaymentProof(): HasOne
    {
        return $this->hasOne(PaymentProof::class)->latestOfMany();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * A proof can be sent while the hold is still running (Slot Held) or after the Admin
     * flagged the last one (Action Needed). Never in any other status.
     */
    public function canUploadProof(): bool
    {
        return match ($this->status) {
            ReservationStatus::SlotHeld => (bool) $this->hold_expires_at?->isFuture(),
            ReservationStatus::ActionNeeded => true,
            default => false,
        };
    }

    /** Name to show for the booker: the guest account's name, or the walk-in's typed name. */
    public function bookerName(): string
    {
        return $this->is_walk_in ? (string) $this->guest_name : (string) $this->user?->name;
    }
}
