<?php

namespace App\Enums;

enum Duration: string
{
    case WholeDay = 'whole_day';
    case HalfDay = 'half_day';

    public function label(): string
    {
        return match ($this) {
            self::WholeDay => 'Whole Day',
            self::HalfDay => 'Half Day',
        };
    }
}
