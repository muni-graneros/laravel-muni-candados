<?php

use App\Support\ReporteDeErrores;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

/*
 * Fixture de texto: el bootstrap de un sistema que cumple. Los proxies de
 * confianza NO se declaran acá —la configuración todavía no está cargada—
 * sino en el AppServiceProvider, y Sentry se engancha solo si las trazas van
 * a un destino propio.
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['csp-report']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Una traza lleva ruta, consulta y a veces el cuerpo del request: en un
        // sistema municipal, datos de un vecino. Se comprueba el destino.
        if (class_exists(Integration::class) && ReporteDeErrores::vaADestinoPropio()) {
            Integration::handles($exceptions);
        }
    })->create();
