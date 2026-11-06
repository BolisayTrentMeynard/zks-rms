<?php

namespace App\Providers;

use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // The chat AI: a few messages a minute per visitor so one person cannot use up the free quota.
        RateLimiter::for('chat', fn (Request $request) => Limit::perMinute((int) config('zk.chat.per_minute'))->by($request->ip()));

        // Sign-ups: 5 per minute per visitor (IP), so nobody can flood the User List.
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Account events for the Activity Log. Failed logins are not recorded.
        Event::listen(Registered::class, fn (Registered $e) => ActivityLogger::account('registered', $e->user, 'Guest account registered'));
        Event::listen(Login::class, fn (Login $e) => ActivityLogger::account('login', $e->user, 'Logged in'));
        Event::listen(Logout::class, function (Logout $e) {
            if ($e->user) {
                ActivityLogger::account('logout', $e->user, 'Logged out');
            }
        });
    }
}
