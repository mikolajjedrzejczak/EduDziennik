<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // F-004 owns the session routes; optional starter endpoints stay disabled.
        Fortify::ignoreRoutes();
    }
}
