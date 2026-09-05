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
