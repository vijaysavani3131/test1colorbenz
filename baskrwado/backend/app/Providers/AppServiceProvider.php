<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind domain services here when interfaces are introduced.
    }

    public function boot(): void
    {
        // Keep boot intentionally small for the API MVP.
    }
}
