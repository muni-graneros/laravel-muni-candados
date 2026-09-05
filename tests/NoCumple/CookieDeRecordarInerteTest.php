<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Muni\Candados\Candados\CookieDeRecordarInerte;
use PHPUnit\Framework\AssertionFailedError;

/*
 * La contraprueba del candado de la cookie de recordar: un Laravel recién
 * instalado, sin el middleware que la ignora ni en «web» ni en el panel, con
 * un acceso fuera del panel que honra «remember» y una migración que no
 * olvida nada (tests/TestCaseNoCumple). Cada comprobación del candado tiene
 * que caer acá; si alguna pasa, no está vigilando nada.
 */
uses(RefreshDatabase::class);

$candado = new CookieDeRecordarInerte;

it('detecta que la portada revive la sesión desde la cookie', function () use ($candado): void {
    expect(fn () => $candado->laPortadaNoAutenticaDesdeLaCookieYLaVenceEnElNavegador())
        ->toThrow(AssertionFailedError::class);
});

it('detecta que la pantalla de acceso de fuera del panel revive la sesión desde la cookie', function () use ($candado): void {
    // El «guest» resuelve al usuario, lo encuentra por la cookie y redirige:
    // ya no responde 200 y ya no vence nada.
    expect(fn () => $candado->laPantallaDeAccesoDeFueraDelPanelTampocoAutenticaDesdeLaCookie())
        ->toThrow(AssertionFailedError::class);
});

it('detecta que el panel deja entrar con la cookie en vez de pedir la contraseña', function () use ($candado): void {
    expect(fn () => $candado->elPanelVenceLaCookieAdemasDeRechazarla())
        ->toThrow(AssertionFailedError::class);
});

it('detecta la migración que no olvida los testigos repartidos', function () use ($candado): void {
    expect(fn () => $candado->laMigracionOlvidaLosTestigosRepartidosAntesDelArreglo())
        ->toThrow(AssertionFailedError::class, 'quedaron testigos de «Recordarme» válidos en la tabla');
});

it('detecta el formulario de acceso que vuelve a repartir la cookie', function () use ($candado): void {
    expect(fn () => $candado->elFormularioDeAccesoDeFueraDelPanelNoDejaCookieDeRecordarAunqueLaPidan())
        ->toThrow(AssertionFailedError::class);
});

it('sin la cookie no toca nada, tampoco acá: es una comprobación de higiene, no un detector', function () use ($candado): void {
    $candado->sinLaCookieNoTocaNada();
});

it('falla claro si la migración no existe donde se la espera', function (): void {
    $candado = new CookieDeRecordarInerte(migracion: base_path('database/migrations/no_existe.php'));

    expect(fn () => $candado->laMigracionOlvidaLosTestigosRepartidosAntesDelArreglo())
        ->toThrow(AssertionFailedError::class, 'falta la migración que olvida los testigos de «Recordarme»');
});
