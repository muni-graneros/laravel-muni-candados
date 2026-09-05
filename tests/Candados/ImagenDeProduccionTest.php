<?php

declare(strict_types=1);

use Muni\Candados\Candados\ImagenDeProduccion;
use PHPUnit\Framework\AssertionFailedError;

$fixtures = dirname(__DIR__).'/Fixtures';

$cumple = new ImagenDeProduccion($fixtures.'/Cumple/Dockerfile');
$noCumple = new ImagenDeProduccion($fixtures.'/NoCumple/Dockerfile');
$chequeoHeredado = new ImagenDeProduccion($fixtures.'/NoCumple/Dockerfile.chequeo-heredado');

it('pasa con la imagen fijada, el chequeo propio en forma shell y curl instalado', function () use ($cumple): void {
    $cumple->laImagenBaseEstaFijadaAUnaVersionConcreta();
    $cumple->laImagenSabeDecirSiEstaSanaYLoDiceConUnaShell();
    $cumple->laImagenTraeLaHerramientaConLaQueSeCompruebaASiMisma();
});

it('detecta la etiqueta flotante de la imagen base', function () use ($noCumple): void {
    expect(fn () => $noCumple->laImagenBaseEstaFijadaAUnaVersionConcreta())
        ->toThrow(AssertionFailedError::class, '«1-php8.5-alpine» es una etiqueta flotante');
});

it('detecta el chequeo en forma exec, donde el «|| exit 1» no decide nada', function () use ($noCumple): void {
    expect(fn () => $noCumple->laImagenSabeDecirSiEstaSanaYLoDiceConUnaShell())
        ->toThrow(AssertionFailedError::class, 'el HEALTHCHECK usa la forma exec');
});

it('detecta el chequeo heredado contra la API admin de Caddy', function () use ($chequeoHeredado): void {
    expect(fn () => $chequeoHeredado->laImagenSabeDecirSiEstaSanaYLoDiceConUnaShell())
        ->toThrow(AssertionFailedError::class, 'volvió a comprobar la API admin de Caddy');
});

it('detecta que curl no se instala aunque el chequeo lo use', function () use ($noCumple): void {
    expect(fn () => $noCumple->laImagenTraeLaHerramientaConLaQueSeCompruebaASiMisma())
        ->toThrow(AssertionFailedError::class, 'el HEALTHCHECK usa curl y la imagen no lo instala');
});

it('mira otra imagen base si el sistema no parte de FrankenPHP', function () use ($fixtures): void {
    $otraBase = new ImagenDeProduccion($fixtures.'/Cumple/Dockerfile', imagenBase: 'php');

    expect(fn () => $otraBase->laImagenBaseEstaFijadaAUnaVersionConcreta())
        ->toThrow(AssertionFailedError::class, 'el Dockerfile ya no parte de una imagen de php');
});

it('falla claro si el Dockerfile no existe', function () use ($fixtures): void {
    $candado = new ImagenDeProduccion($fixtures.'/NoExiste/Dockerfile');

    expect(fn () => $candado->laImagenBaseEstaFijadaAUnaVersionConcreta())
        ->toThrow(AssertionFailedError::class, 'no existe el Dockerfile');
});
