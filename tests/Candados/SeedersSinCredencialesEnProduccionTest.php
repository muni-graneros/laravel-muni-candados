<?php

declare(strict_types=1);

use Muni\Candados\Candados\SeedersSinCredencialesEnProduccion;
use PHPUnit\Framework\Assert;
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

/*
 * El mismo hueco estructural que PwaSinRestosDelScaffold (2026-09-10): un
 * sistema sin ningún seeder que llame Hash::make es legítimo (no todo
 * sistema siembra credenciales de demo), pero antes la aserción vivía DENTRO
 * del bucle, gateada por archivo: con cero seeders que la disparen, el
 * bucle corría cero veces y el test quedaba sin ninguna aserción real.
 */
it('un directorio de seeders sin ninguno que siembre credenciales deja constancia de que se revisó', function (): void {
    $ruta = sys_get_temp_dir().'/seeders-sin-credenciales-'.uniqid();
    mkdir($ruta);
    file_put_contents(
        $ruta.'/CategoriasSeeder.php',
        "<?php\nnamespace Database\Seeders;\nuse Illuminate\Database\Seeder;\nclass CategoriasSeeder extends Seeder { public function run(): void { /* sin credenciales */ } }\n"
    );

    $candado = new SeedersSinCredencialesEnProduccion($ruta);

    try {
        $antes = Assert::getCount();

        $candado->laGuardiaDeEntornoVaAntesDeLaPrimeraContrasena();

        expect(Assert::getCount())->toBeGreaterThan(
            $antes,
            'sin ningún seeder que siembre credenciales, la comprobación no dejó ninguna aserción: '.
            'vuelve a quedar muda ante el caso vacío'
        );
    } finally {
        unlink($ruta.'/CategoriasSeeder.php');
        rmdir($ruta);
    }
});
