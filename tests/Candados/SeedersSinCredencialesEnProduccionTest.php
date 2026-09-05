<?php

declare(strict_types=1);

use Muni\Candados\Candados\SeedersSinCredencialesEnProduccion;
use PHPUnit\Framework\AssertionFailedError;

$fixtures = dirname(__DIR__).'/Fixtures';

$cumple = new SeedersSinCredencialesEnProduccion($fixtures.'/Cumple/database/seeders');
$noCumple = new SeedersSinCredencialesEnProduccion($fixtures.'/NoCumple/database/seeders');

it('pasa con seeders que miran el entorno antes de sembrar', function () use ($cumple): void {
    $cumple->ningunSeederSiembraCredencialesSinExcluirProduccion();
    $cumple->laGuardiaDeEntornoVaAntesDeLaPrimeraContrasena();
});

it('detecta el seeder que siembra credenciales sin mirar nunca el entorno', function () use ($noCumple): void {
    expect(fn () => $noCumple->ningunSeederSiembraCredencialesSinExcluirProduccion())
        ->toThrow(AssertionFailedError::class, 'siembran credenciales también en producción: SinGuardiaSeeder.php');
});

it('detecta la guardia que llega después de la primera contraseña', function () use ($noCumple): void {
    expect(fn () => $noCumple->laGuardiaDeEntornoVaAntesDeLaPrimeraContrasena())
        ->toThrow(AssertionFailedError::class, 'GuardiaTardiaSeeder.php: siembra una contraseña antes de mirar el entorno');
});

it('falla claro si el directorio de seeders no existe', function () use ($fixtures): void {
    $candado = new SeedersSinCredencialesEnProduccion($fixtures.'/NoExiste/database/seeders');

    expect(fn () => $candado->ningunSeederSiembraCredencialesSinExcluirProduccion())
        ->toThrow(AssertionFailedError::class, 'no existe el directorio de seeders');
});
