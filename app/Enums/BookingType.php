<?php

namespace App\Enums;

enum BookingType: string
{
    case Pool = 'pool';
    case Transient = 'transient';
    case Room = 'room';
    case VenueHall = 'venue_hall';

    public function label(): string
    {
        return match ($this) {
            self::Pool => 'Pool',
            self::Transient => 'Transient',
            self::Room => 'Room',
            self::VenueHall => 'Venue Hall',
        };
    }

    /** Maximum pax for this type, or null when there is no limit (Pool, and Venue Hall until the client gives a number). */
    public function paxCap(): ?int
    {
        return config('zk.pax_caps.'.$this->value);
    }
}
