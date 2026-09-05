<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

/*
 * Fixture de texto: el bootstrap que los candados tienen que rechazar. Los
 * proxies se declaran acá con un comodín (y otra vez, distinto, en el
 * AppServiceProvider), y Sentry se engancha con `class_exists` a secas: la
 * clase existe siempre, así que el enganche es incondicional.
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Reporte de errores (si hay DSN configurado)
        if (class_exists(Integration::class)) {
            Integration::handles($exceptions);
        }
    })->create();
