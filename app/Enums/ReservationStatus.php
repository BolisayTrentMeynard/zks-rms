<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case SlotHeld = 'slot_held';
    case VerifyingPayment = 'verifying_payment';
    case Confirmed = 'confirmed';
    case ActionNeeded = 'action_needed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /** The six badge labels. Use these exact words everywhere. */
    public function label(): string
    {
        return match ($this) {
            self::SlotHeld => 'Slot Held',
            self::VerifyingPayment => 'Verifying Payment',
            self::Confirmed => 'Confirmed',
            self::ActionNeeded => 'Action Needed',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Statuses that keep the date blocked for the whole resort. */
    public function blocksDate(): bool
    {
        return match ($this) {
            self::SlotHeld, self::VerifyingPayment, self::Confirmed, self::ActionNeeded => true,
            self::Expired, self::Cancelled => false,
        };
    }

    /** @return list<self> */
    public static function blocking(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => $s->blocksDate()));
    }
}
