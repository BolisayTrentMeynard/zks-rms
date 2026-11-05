<?php

namespace App\Providers;

use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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
