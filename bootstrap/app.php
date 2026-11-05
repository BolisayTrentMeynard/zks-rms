<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);

        // Sign out a deactivated account on its next request.
        $middleware->web(append: [EnsureAccountIsActive::class]);

        // Visitors who need to log in go to the login page; logged-in users who open
        // login/register go to the page for their role.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => auth()->user()?->isAdmin()
            ? route('admin.dashboard')
            : route('availability'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
