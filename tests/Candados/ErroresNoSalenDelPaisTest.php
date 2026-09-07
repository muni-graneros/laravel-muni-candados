<?php

declare(strict_types=1);

use Muni\Candados\Candados\ErroresNoSalenDelPais;
use Muni\Candados\Tests\Fixtures\App\Support\ReporteDeErrores;
use Muni\Candados\Tests\Fixtures\App\Support\ReporteDeErroresQueSeCreeTodo;
use PHPUnit\Framework\AssertionFailedError;

$fixtures = dirname(__DIR__).'/Fixtures';

$cumple = new ErroresNoSalenDelPais(
    clase: ReporteDeErrores::class,
    bootstrap: $fixtures.'/Cumple/bootstrap/app.php',
);

$noCumple = new ErroresNoSalenDelPais(
    clase: ReporteDeErroresQueSeCreeTodo::class,
    bootstrap: $fixtures.'/NoCumple/bootstrap/app.php',
);

it('pasa con la clase que mira el host del DSN y el bootstrap que la consulta', function () use ($cumple): void {
    $cumple->sinDsnNoSeEnganchaNada();

    foreach (ErroresNoSalenDelPais::DSN_AJENOS as $dsn) {
        $cumple->rechazaUnDsnAjeno($dsn);
    }

    foreach (ErroresNoSalenDelPais::DSN_PROPIOS as $dsn) {
        $cumple->aceptaUnDsnPropio($dsn);
    }

    foreach (ErroresNoSalenDelPais::DSN_ILEGIBLES as $dsn) {
        $cumple->noDaPorBuenoUnDsnIlegible($dsn);
    }

    $cumple->elBootstrapUsaLaComprobacion();
});

it('detecta la clase que se cree cualquier DSN, sentry.io incluido', function () use ($noCumple): void {
    expect(fn () => $noCumple->rechazaUnDsnAjeno('https://clave@o123456.ingest.sentry.io/4501'))
        ->toThrow(AssertionFailedError::class, 'se aceptó: las trazas del sistema se irían a un servicio extranjero');
});

it('detecta la clase que mira con str_contains: el subdominio que termina en sentry.io pasa', function () use ($fixtures): void {
    $strContains = new class
    {
        public static function vaADestinoPropio(): bool
        {
            $dsn = (string) config('sentry.dsn');

            return $dsn !== '' && ! str_contains($dsn, '@sentry.io') && ! str_contains($dsn, '.ingest.');
        }
    };

    $candado = new ErroresNoSalenDelPais(clase: $strContains::class, bootstrap: $fixtures.'/Cumple/bootstrap/app.php');

    expect(fn () => $candado->rechazaUnDsnAjeno('https://clave@errores.sentry.io/4501'))
        ->toThrow(AssertionFailedError::class, '«https://clave@errores.sentry.io/4501» se aceptó');
});

it('detecta el DSN ilegible que se da por bueno', function () use ($noCumple): void {
    expect(fn () => $noCumple->noDaPorBuenoUnDsnIlegible('https://'))
        ->toThrow(AssertionFailedError::class, '«https://» no se puede interpretar y aun así se dio por bueno');
});

it('detecta el bootstrap que engancha Sentry con class_exists a secas', function () use ($noCumple): void {
    expect(fn () => $noCumple->elBootstrapUsaLaComprobacion())
        ->toThrow(AssertionFailedError::class, 'bootstrap/app.php engancha Sentry sin comprobar a dónde van las trazas: falta ReporteDeErroresQueSeCreeTodo::vaADestinoPropio()');
});

it('falla claro si la clase que decide no existe', function () use ($fixtures): void {
    $candado = new ErroresNoSalenDelPais(clase: 'App\\Support\\NoExiste', bootstrap: $fixtures.'/Cumple/bootstrap/app.php');

    expect(fn () => $candado->sinDsnNoSeEnganchaNada())
        ->toThrow(AssertionFailedError::class, 'no existe App\Support\NoExiste::vaADestinoPropio()');
});

/*
 * La clase que decide se resuelve por lo que EXISTE, no por lo que se supone.
 *
 * El default apuntaba a `App\Support\ReporteDeErrores`, la copia local. En
 * cuanto un sistema adoptaba la del paquete y borraba la suya, `Candados::todos()`
 * fallaba señalando una clase recién borrada, y el sistema tenía que dejar de
 * usar `todos()` y registrar los ocho candados a mano — lo contrario de lo que
 * promete este paquete. Pasó de verdad al adoptar en `rrhh-graneros`.
 */
it('prefiere la clase local cuando el sistema todavía tiene la suya', function (): void {
    $candado = new ErroresNoSalenDelPais(candidatas: [
        ClaseQueDecideLocal::class,
        ClaseQueDecideDelPaquete::class,
    ]);

    expect($candado->claseQueDecide())->toBe(ClaseQueDecideLocal::class);
});

it('cae en la del paquete cuando el sistema ya borró la suya', function (): void {
    $candado = new ErroresNoSalenDelPais(candidatas: [
        'App\\Support\\QueNoExisteEnNingunLado',
        ClaseQueDecideDelPaquete::class,
    ]);

    expect($candado->claseQueDecide())->toBe(ClaseQueDecideDelPaquete::class);
});

it('falla nombrando las dos cuando no existe ninguna', function (): void {
    $candado = new ErroresNoSalenDelPais(candidatas: [
        'App\\Support\\QueNoExisteEnNingunLado',
        'Muni\\Shared\\Errores\\TampocoExiste',
    ]);

    expect(fn () => $candado->claseQueDecide())
        ->toThrow(AssertionFailedError::class, 'Se buscó: App\\Support\\QueNoExisteEnNingunLado');
});

it('una clase pasada a mano gana sobre la resolución automática', function (): void {
    $candado = new ErroresNoSalenDelPais(
        clase: ClaseQueDecideDelPaquete::class,
        candidatas: [ClaseQueDecideLocal::class],
    );

    expect($candado->claseQueDecide())->toBe(ClaseQueDecideDelPaquete::class);
});

class ClaseQueDecideLocal
{
    public static function vaADestinoPropio(): bool
    {
        return true;
    }
}

class ClaseQueDecideDelPaquete
{
    public static function vaADestinoPropio(): bool
    {
        return true;
    }
}
