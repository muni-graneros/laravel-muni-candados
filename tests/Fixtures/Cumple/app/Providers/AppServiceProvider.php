<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;

/**
 * Fixture de texto: la lista de proxies se declara UNA vez, acá, y sale de la
 * configuración.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        TrustProxies::at(config('proxies.confiables'));
    }
}
