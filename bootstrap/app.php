<?php

use App\Http\Middleware\EnsureMfaVerified;
use App\Http\Middleware\EnsurePasswordNotExpired;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'mfa' => EnsureMfaVerified::class,
            'password.fresh' => EnsurePasswordNotExpired::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            if (! $request->expectsJson()) {
                $request->session()->flash('status', 'Please sign in. Sessions end after 15 minutes of inactivity.');
            }

            return route('login');
        });

        $middleware->redirectUsersTo(fn (): string => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
