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

/*
 * El falso positivo real (encontrado en seguridad-graneros, 2026-09-07): un
 * service worker propio, con tests/Feature/PwaPatrulleroTest.php encima y
 * dos auditorías de seguridad, al que solo le falta la línea de
 * `serviceWorker.register()`. El candado, tal como estaba, lo marcaba en
 * rojo con el mismo «Bórralo» que usa para el sobrante real del scaffold —
 * empujando a tirar trabajo probado. Esta fixture (`pwa-terreno`) imita
 * exactamente ese caso: sw.js propio (no calza con el hash del scaffold) +
 * prueba dedicada en tests/ + ninguna vista lo registra.
 */
$funcionSinRegistrar = new PwaSinRestosDelScaffold(
    $fixtures.'/NoCumple/pwa-terreno/public',
    $fixtures.'/NoCumple/pwa-terreno/resources/views',
    $fixtures.'/NoCumple/pwa-terreno/tests',
);

it('no empuja a borrar un service worker propio con prueba dedicada: pide el registro, no el borrado', function () use ($funcionSinRegistrar): void {
    try {
        $funcionSinRegistrar->noSeSirveNingunServiceWorkerQueNadieRegistre();
        test()->fail('se esperaba que el candado fallara: nadie registra el service worker.');
    } catch (AssertionFailedError $excepcion) {
        $mensaje = $excepcion->getMessage();

        expect(str_contains($mensaje, 'Bórralo'))
            ->toBeFalse('el mensaje sigue empujando a borrar código con prueba dedicada: '.$mensaje);

        expect(str_contains($mensaje, 'No lo borres'))
            ->toBeTrue('el mensaje debería decir explícitamente que no hay que borrarlo: '.$mensaje);
    }
});

/*
 * El reverso, para no perder la otra mitad de la regla: si el archivo SÍ es
 * byte a byte el que reparte el scaffold, el mensaje tiene que seguir
 * empujando a borrar (ahora con la evidencia del hash, no por sospecha).
 */
it('sigue empujando a borrar el resto real del scaffold (mismo SHA-256 que el original)', function () use ($fixtures): void {
    $andamio = new PwaSinRestosDelScaffold(
        $fixtures.'/NoCumple/public',
        $fixtures.'/NoCumple/resources/views',
        $fixtures.'/pwa-tests-vacio',
    );

    expect(fn () => $andamio->noSeSirveNingunServiceWorkerQueNadieRegistre())
        ->toThrow(AssertionFailedError::class, 'Bórralo');
});

/*
 * Sin evidencia en ningún sentido (ni el hash del scaffold, ni una prueba
 * dedicada), el mensaje no tiene que empujar a NINGUNA de las dos acciones:
 * ese es justo el defecto que se está arreglando, en cualquier dirección.
 */
it('sin evidencia en ningún sentido, no empuja ni a borrar ni a asumir que está construido', function () use ($fixtures): void {
    $ambiguo = new PwaSinRestosDelScaffold(
        $fixtures.'/NoCumple/pwa-ambigua/public',
        $fixtures.'/NoCumple/pwa-ambigua/resources/views',
        $fixtures.'/pwa-tests-vacio',
    );

    try {
        $ambiguo->noSeSirveNingunServiceWorkerQueNadieRegistre();
        test()->fail('se esperaba que el candado fallara: nadie registra el service worker.');
    } catch (AssertionFailedError $excepcion) {
        $mensaje = $excepcion->getMessage();

        expect(str_contains($mensaje, 'Bórralo'))->toBeFalse('empuja a borrar sin evidencia: '.$mensaje);
        expect(str_contains($mensaje, 'no lo borres'))->toBeFalse('asume construido sin evidencia: '.$mensaje);
    }
});

/*
 * Las excepciones declaradas exigen motivo escrito: una lista de nombres
 * sueltos se llena de archivos sin que nadie recuerde por qué.
 */
it('una excepción declarada con motivo exime el archivo, sin importar el resto de la evidencia', function () use ($fixtures): void {
    $conExcepcion = new PwaSinRestosDelScaffold(
        $fixtures.'/NoCumple/public',
        $fixtures.'/NoCumple/resources/views',
        $fixtures.'/pwa-tests-vacio',
        ['sw.js' => 'Migración en curso al service worker nuevo: se retira en la tarea MUNI-1234.'],
    );

    // No lanza: la excepción con motivo corta la evaluación antes del hash o el test dedicado.
    $conExcepcion->noSeSirveNingunServiceWorkerQueNadieRegistre();

    expect(true)->toBeTrue();
});

it('una excepción sin motivo no exime nada: falla pidiendo que se escriba por qué', function () use ($fixtures): void {
    $sinMotivo = new PwaSinRestosDelScaffold(
        $fixtures.'/NoCumple/public',
        $fixtures.'/NoCumple/resources/views',
        $fixtures.'/pwa-tests-vacio',
        ['sw.js' => ''],
    );

    expect(fn () => $sinMotivo->noSeSirveNingunServiceWorkerQueNadieRegistre())
        ->toThrow(AssertionFailedError::class, 'no trae motivo');
});
