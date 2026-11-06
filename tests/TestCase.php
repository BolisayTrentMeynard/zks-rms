<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests should not depend on a built front end (npm run build).
        $this->withoutVite();

        // Never let a real .env (with a real chat key) leak into tests: no test may call the AI provider.
        // ChatTest switches a fake provider on for itself.
        config([
            'zk.chat.api_key' => '',
            'zk.chat.base_url' => '',
            'zk.chat.model' => '',
            'zk.chat.daily_cap' => 300,
            'zk.chat.per_minute' => 8,
            'zk.chat.max_tokens' => 400,
            'zk.chat.timeout' => 8,
        ]);
    }
}
