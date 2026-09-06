<?php

declare(strict_types=1);

use Muni\Candados\Candados\PwaSinRestosDelScaffold;
use PHPUnit\Framework\AssertionFailedError;

$fixtures = dirname(__DIR__).'/Fixtures';

$cumple = new PwaSinRestosDelScaffold(
    $fixtures.'/Cumple/public',
    $fixtures.'/Cumple/resources/views',
);

$noCumple = new PwaSinRestosDelScaffold(
    $fixtures.'/NoCumple/public',
    $fixtures.'/NoCumple/resources/views',
);

it('pasa cuando el worker y el manifest servidos son los que la vista usa', function () use ($cumple): void {
    $cumple->noSeSirveNingunServiceWorkerQueNadieRegistre();
    $cumple->noSeSirveNingunManifestQueNadieEnlace();
    $cumple->cadaServiceWorkerRegistradoExiste();
});

it('detecta el service worker huérfano del scaffold', function () use ($noCumple): void {
    expect(fn () => $noCumple->noSeSirveNingunServiceWorkerQueNadieRegistre())
        ->toThrow(AssertionFailedError::class, 'ninguna vista lo registra');
});

it('detecta el manifest huérfano, que declara una identidad que nadie usa', function () use ($noCumple): void {
    expect(fn () => $noCumple->noSeSirveNingunManifestQueNadieEnlace())
        ->toThrow(AssertionFailedError::class, 'ninguna vista lo enlaza');
});

it('detecta la vista que registra un worker que no está en public/', function () use ($noCumple): void {
    expect(fn () => $noCumple->cadaServiceWorkerRegistradoExiste())
        ->toThrow(AssertionFailedError::class, 'sw-que-no-existe.js');
});

it('falla claro si el sistema no tiene directorio de vistas', function () use ($fixtures): void {
    $sinVistas = new PwaSinRestosDelScaffold($fixtures.'/Cumple/public', $fixtures.'/Cumple/no-existe');

    expect(fn () => $sinVistas->noSeSirveNingunServiceWorkerQueNadieRegistre())
        ->toThrow(AssertionFailedError::class, 'no existe el directorio de vistas');
});
