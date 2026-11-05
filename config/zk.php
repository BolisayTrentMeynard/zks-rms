<?php

return [

    /*
    | Max pax per Booking Type. null = no limit.
    | Venue Hall stays null until the client gives a number (one line to change).
    */
    'pax_caps' => [
        'pool' => null,
        'transient' => 7,
        'room' => 12,
        'venue_hall' => null,
    ],

    // Slot Held lasts this many hours; the guest gets a nudge this many hours before it ends.
    'hold_hours' => 24,
    'hold_reminder_hours' => 2,

    // GCash payment proof uploads: private disk, image only.
    'proof_disk' => 'local',
    'proof_max_kb' => 5120,

    // Earliest bookable date online, counted from today (1 = tomorrow, 0 = same day).
    // Same-day bookings are entered by the Admin as walk-ins.
    'min_advance_days' => 1,

    // Reschedule / cancel must be requested at least this many days before the reserved date.
    'notice_days' => 5,

    // The one shared Admin account, created by the seeder. Values come from .env.
    'admin' => [
        'name' => env('ADMIN_NAME', 'ZKs Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
