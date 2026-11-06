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

    // Shown in the FAQ and the chat when a guest needs the resort itself. Not in the project docs yet,
    // so both stay empty until the client gives them. Set RESORT_CONTACT and RESORT_GCASH in .env
    // (free text, for example a mobile number); no code edit and nothing in git.
    'contact' => env('RESORT_CONTACT'),
    'gcash' => env('RESORT_GCASH'),

    // The AI behind the chat panel (Chunk 7c). One OpenAI-compatible provider, set in .env.
    // With no key the chat simply keeps using the built-in keyword answers.
    'chat' => [
        'base_url' => env('CHAT_BASE_URL'),
        'model' => env('CHAT_MODEL'),
        'api_key' => env('CHAT_API_KEY'),
        'timeout' => (int) env('CHAT_TIMEOUT', 8),            // seconds before we give up and use the fallback
        'daily_cap' => (int) env('CHAT_DAILY_CAP', 300),      // AI calls per day for the whole site
        'per_minute' => (int) env('CHAT_PER_MINUTE', 8),      // messages per minute per visitor (IP)
        'max_chars' => 300,                                   // longest typed question
        'history_messages' => 6,                              // earlier messages sent along for context
        'max_tokens' => (int) env('CHAT_MAX_TOKENS', 400),    // reply length cap
    ],

    // The one shared Admin account, created by the seeder. Values come from .env.
    'admin' => [
        'name' => env('ADMIN_NAME', 'ZKs Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
