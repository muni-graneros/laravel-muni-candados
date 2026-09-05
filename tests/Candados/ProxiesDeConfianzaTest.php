<?php

declare(strict_types=1);

use Illuminate\Http\Middleware\TrustProxies;
use Muni\Candados\Candados\ProxiesDeConfianza;
use PHPUnit\Framework\AssertionFailedError;

$fixtures = dirname(__DIR__).'/Fixtures';

$cumple = new ProxiesDeConfianza(
    bootstrap: $fixtures.'/Cumple/bootstrap/app.php',
    proveedor: $fixtures.'/Cumple/app/Providers/AppServiceProvider.php',
);

$noCumple = new ProxiesDeConfianza(
    bootstrap: $fixtures.'/NoCumple/bootstrap/app.php',
    proveedor: $fixtures.'/NoCumple/app/Providers/AppServiceProvider.php',
);

it('pasa cuando la lista viene de la configuración, se declara una vez y no tiene comodines', function () use ($cumple): void {
    $cumple->noSeConfiaEnCualquierProxy();
    $cumple->laListaDeProxiesSaleDeLaConfiguracion();
    $cumple->laListaSeDeclaraUnaVezYDesdeLaConfiguracion();
    $cumple->unaCabeceraDeOrigenFalsificadaNoCambiaLaDireccionQueVeLaAplicacion();
});

it('detecta el comodín que confía en cualquier proxy', function () use ($cumple): void {
    TrustProxies::at('*');

    expect(fn () => $cumple->noSeConfiaEnCualquierProxy())
        ->toThrow(AssertionFailedError::class, 'se confía en cualquier proxy');
});

it('detecta el comodín también escondido dentro de una lista', function () use ($cumple): void {
    TrustProxies::at(['127.0.0.1', '**']);

    expect(fn () => $cumple->noSeConfiaEnCualquierProxy())
        ->toThrow(AssertionFailedError::class, 'se confía en cualquier proxy');
});

it('detecta que falta la opción de configuración', function () use ($cumple): void {
    config()->set('proxies.confiables', null);

    expect(fn () => $cumple->laListaDeProxiesSaleDeLaConfiguracion())
        ->toThrow(AssertionFailedError::class, 'no hay una opción de configuración «proxies.confiables»');
});

it('detecta la lista declarada dos veces, con la del bootstrap fija en el código', function () use ($noCumple): void {
    expect(fn () => $noCumple->laListaSeDeclaraUnaVezYDesdeLaConfiguracion())
        ->toThrow(AssertionFailedError::class, 'bootstrap/app.php vuelve a declarar los proxies');
});

it('detecta el AppServiceProvider que fija la lista en vez de leer la configuración', function () use ($fixtures): void {
    $candado = new ProxiesDeConfianza(
        bootstrap: $fixtures.'/Cumple/bootstrap/app.php',
        proveedor: $fixtures.'/NoCumple/app/Providers/AppServiceProvider.php',
    );

    expect(fn () => $candado->laListaSeDeclaraUnaVezYDesdeLaConfiguracion())
        ->toThrow(AssertionFailedError::class, "no declara los proxies desde la configuración: falta TrustProxies::at(config('proxies.confiables'))");
});

it('detecta que una cabecera falsificada cambia la dirección que ve la aplicación', function () use ($cumple): void {
    // Con el comodín, la aplicación se cree X-Forwarded-For venga de donde venga.
    TrustProxies::at('*');

    expect(fn () => $cumple->unaCabeceraDeOrigenFalsificadaNoCambiaLaDireccionQueVeLaAplicacion())
        ->toThrow(AssertionFailedError::class, 'la aplicación se creyó la dirección declarada en la cabecera');
});

it('mira otra clave si el sistema llama distinto a su configuración', function () use ($fixtures): void {
    config()->set('trustedproxy.proxies', ['10.0.0.1']);

    $candado = new ProxiesDeConfianza(
        claveDeConfiguracion: 'trustedproxy.proxies',
        bootstrap: $fixtures.'/Cumple/bootstrap/app.php',
        proveedor: $fixtures.'/Cumple/app/Providers/AppServiceProvider.php',
    );

    $candado->laListaDeProxiesSaleDeLaConfiguracion();

    expect(fn () => $candado->laListaSeDeclaraUnaVezYDesdeLaConfiguracion())
        ->toThrow(AssertionFailedError::class, "falta TrustProxies::at(config('trustedproxy.proxies'))");
});
