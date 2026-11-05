<?php

namespace App\Enums;

enum ChangeRequest: string
{
    case Reschedule = 'reschedule';
    case Cancel = 'cancel';

    public function label(): string
    {
        return match ($this) {
            self::Reschedule => 'Reschedule requested',
            self::Cancel => 'Cancellation requested',
        };
    }
}
