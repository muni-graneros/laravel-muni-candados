<?php

declare(strict_types=1);

use Muni\Candados\Candados\NadieEmiteCookieDeRecordar;
use PHPUnit\Framework\AssertionFailedError;

$fixtures = dirname(__DIR__).'/Fixtures';

$cumple = new NadieEmiteCookieDeRecordar([$fixtures.'/Cumple/app', $fixtures.'/Cumple/routes']);
$noCumple = new NadieEmiteCookieDeRecordar([$fixtures.'/NoCumple/app', $fixtures.'/NoCumple/routes']);

it('pasa con código que no pide la cookie por ningún lado', function () use ($cumple): void {
    $cumple->ningunCodigoDelSistemaVuelveAPedirLaCookieDeRecordar();
});

it('detecta cada forma de volver a pedir la cookie, nombrando el archivo', function () use ($noCumple): void {
    try {
        $noCumple->ningunCodigoDelSistemaVuelveAPedirLaCookieDeRecordar();
    } catch (AssertionFailedError $falla) {
        $mensaje = $falla->getMessage();

        expect(str_contains($mensaje, 'Vuelve a emitirse la cookie de recordar en:'))->toBeTrue($mensaje);
        expect(str_contains($mensaje, "AccesoController.php → \$request->boolean('remember')"))->toBeTrue($mensaje);
        expect(str_contains($mensaje, 'AccesoController.php → Auth::attempt($credenciales, …) con segundo argumento'))->toBeTrue($mensaje);
        expect(str_contains($mensaje, 'web.php → Auth::login(…, remember: true)'))->toBeTrue($mensaje);

        return;
    }

    $this->fail('el candado no detectó ninguna de las tres formas de pedir la cookie');
});

it('nombra el archivo relativo a la raíz del proyecto cuando está dentro', function (): void {
    // Sobre la raíz de la aplicación de mentira (tests/Fixtures/Cumple), con los
    // valores por omisión: app/ y routes/ de esa raíz.
    $candado = new NadieEmiteCookieDeRecordar;

    $candado->ningunCodigoDelSistemaVuelveAPedirLaCookieDeRecordar();

    $conHallazgos = new NadieEmiteCookieDeRecordar([base_path('../NoCumple/app')]);

    expect(fn () => $conHallazgos->ningunCodigoDelSistemaVuelveAPedirLaCookieDeRecordar())
        ->toThrow(AssertionFailedError::class, 'NoCumple/app/Http/Controllers/AccesoController.php → ');
});

it('falla claro si un directorio a revisar no existe', function () use ($fixtures): void {
    $candado = new NadieEmiteCookieDeRecordar([$fixtures.'/Cumple/app', $fixtures.'/Cumple/no-existe']);

    expect(fn () => $candado->ningunCodigoDelSistemaVuelveAPedirLaCookieDeRecordar())
        ->toThrow(AssertionFailedError::class, 'no existe el directorio');
});
