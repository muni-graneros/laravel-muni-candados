<?php

declare(strict_types=1);

use Muni\Candados\Candados\GuardaDeCredencialesDePlantilla;
use PHPUnit\Framework\AssertionFailedError;

$fixtures = dirname(__DIR__).'/Fixtures';

$cumple = new GuardaDeCredencialesDePlantilla($fixtures.'/Cumple/composer.json');
$noCumple = new GuardaDeCredencialesDePlantilla($fixtures.'/NoCumple/composer.json');

it('pasa cuando el sistema requiere el paquete y no le apaga el descubrimiento', function () use ($cumple): void {
    $cumple->elPaqueteEstaRequerido();
    $cumple->elProveedorNoEstaExcluidoDelDescubrimiento();
});

it('pasa cuando la versión instalada del paquete ya trae la guarda', function () use ($cumple): void {
    $cumple->laGuardaEstaInstalada();
});

it('pasa cuando lo instalado es una rama del paquete con un alias de versión que ya trae la guarda', function () use ($fixtures): void {
    // Lo que hay en los sistemas mientras laravel-muni-shared 1.22.0 no está
    // etiquetada: `dev-feature/segundo-factor-muni-mfa as 1.22.0`. En
    // installed.json la versión es la de la rama (no se compara con nada); el
    // alias que Composer resolvió queda en installed.php.
    $conAlias = new GuardaDeCredencialesDePlantilla($fixtures.'/CumpleConAlias/composer.json');

    $conAlias->laGuardaEstaInstalada();
});

it('una rama sin alias no cuenta como versión con la guarda', function () use ($fixtures): void {
    $sinAlias = new GuardaDeCredencialesDePlantilla($fixtures.'/NoCumpleRamaSinAlias/composer.json');

    expect(fn () => $sinAlias->laGuardaEstaInstalada())
        ->toThrow(AssertionFailedError::class, 'está instalada la versión dev-feature/sin-alias');
});

it('detecta el sistema que promete el paquete pero tiene instalada una versión sin la guarda', function () use ($noCumple): void {
    // El caso que motivó esta comprobación: `composer.json` con `^1.18` pasa las
    // otras dos y la guarda no existe, porque nace en la 1.19.0.
    expect(fn () => $noCumple->laGuardaEstaInstalada())
        ->toThrow(AssertionFailedError::class, 'está instalada la versión 1.18.0');
});

it('detecta el sistema que nunca requirió el paquete de la guarda', function () use ($noCumple): void {
    expect(fn () => $noCumple->elPaqueteEstaRequerido())
        ->toThrow(AssertionFailedError::class, 'el composer.json no requiere muni-graneros/laravel-muni-shared');
});

it('detecta el composer.json que apaga TODO el auto-descubrimiento', function () use ($fixtures): void {
    $candado = new GuardaDeCredencialesDePlantilla($fixtures.'/NoCumple/composer-descubrimiento-apagado.json');

    expect(fn () => $candado->elProveedorNoEstaExcluidoDelDescubrimiento())
        ->toThrow(AssertionFailedError::class, 'apaga TODO el auto-descubrimiento de paquetes');
});

it('detecta el composer.json que excluye puntualmente el paquete de la guarda', function () use ($fixtures): void {
    $candado = new GuardaDeCredencialesDePlantilla($fixtures.'/NoCumple/composer-paquete-excluido.json');

    expect(fn () => $candado->elProveedorNoEstaExcluidoDelDescubrimiento())
        ->toThrow(AssertionFailedError::class, 'excluye muni-graneros/laravel-muni-shared del auto-descubrimiento');
});

it('falla claro si el composer.json no existe', function () use ($fixtures): void {
    $candado = new GuardaDeCredencialesDePlantilla($fixtures.'/NoExiste/composer.json');

    expect(fn () => $candado->elPaqueteEstaRequerido())
        ->toThrow(AssertionFailedError::class, 'no existe el composer.json');
});

it('falla claro si el composer.json no es JSON válido', function (): void {
    $roto = tempnam(sys_get_temp_dir(), 'composer-roto-');
    file_put_contents($roto, '{ esto no es json');

    $candado = new GuardaDeCredencialesDePlantilla($roto);

    try {
        expect(fn () => $candado->elPaqueteEstaRequerido())
            ->toThrow(AssertionFailedError::class, 'el composer.json no es JSON válido');
    } finally {
        unlink($roto);
    }
});
