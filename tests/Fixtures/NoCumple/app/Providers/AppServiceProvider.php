<?php

namespace App\Providers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;

/**
 * Fixture de texto: la lista fija en el código. Para cambiar el rango del
 * intermediario hay que tocar el repositorio, que es como se termina
 * volviendo a «*».
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        TrustProxies::at(['172.16.0.0/12', '127.0.0.1']);
    }
}
