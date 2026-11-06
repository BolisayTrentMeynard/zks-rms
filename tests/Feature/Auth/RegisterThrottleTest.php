<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_is_limited_to_five_attempts_a_minute_per_visitor(): void
    {
        // Empty forms fail validation (redirect back) but still count as attempts.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [])->assertStatus(302);
        }

        $this->post('/register', [])->assertStatus(429);
    }
}
