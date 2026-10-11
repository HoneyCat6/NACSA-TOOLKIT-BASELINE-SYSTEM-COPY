<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // FR-01 / SEC-01: min 12 chars with upper, lower, digit and symbol.
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());

        Paginator::useBootstrapFive();
    }
}
