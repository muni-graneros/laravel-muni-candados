<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Muni\Candados\Candados\NingunResourceSinAutorizacion;
use Muni\Candados\Tests\Fixtures\App\Filament\Resources\ResourceConPolicyRegistrada;
use Muni\Candados\Tests\Fixtures\App\Filament\Resources\ResourceSinPolicyConCanViewAnyPropio;
use Muni\Candados\Tests\Fixtures\App\Filament\Resources\ResourceSinPolicyNiCanViewAny;
use Muni\Candados\Tests\Fixtures\App\Models\ModeloDePaquete;
use Muni\Candados\Tests\Fixtures\App\Models\OtroModeloDePaquete;
use Muni\Candados\Tests\Fixtures\App\Policies\PoliticaDeOtroModeloDePaquete;
use PHPUnit\Framework\AssertionFailedError;

/**
 * El agujero real: una policy que existe pero que Filament nunca consulta
 * porque el modelo del Resource no vive en `App\Models`, y sin modo estricto
 * la ausencia de policy CONCEDE en vez de denegar (helpers.php:60-95 de
 * filament/filament). Los tres Resources de abajo son fixtures nuevas —no
 * texto de `tests/Fixtures/Cumple` o `NoCumple`— porque este candado no lee
 * archivos: barre `Filament::getPanels()`.
 */
it('pasa cuando el Resource tiene una policy registrada explícitamente para su modelo', function (): void {
    Gate::policy(OtroModeloDePaquete::class, PoliticaDeOtroModeloDePaquete::class);
    Filament::getCurrentOrDefaultPanel()->resources([ResourceConPolicyRegistrada::class]);

    (new NingunResourceSinAutorizacion)->todoResourceAutorizaDeVerdad();
});

it('detecta un Resource sin policy y sin canViewAny propio, nombrando el Resource y el modelo', function (): void {
    Filament::getCurrentOrDefaultPanel()->resources([ResourceSinPolicyNiCanViewAny::class]);

    try {
        (new NingunResourceSinAutorizacion)->todoResourceAutorizaDeVerdad();
        test()->fail('el candado no detectó el Resource sin autorización');
    } catch (AssertionFailedError $falla) {
        $mensaje = $falla->getMessage();

        expect(str_contains($mensaje, ResourceSinPolicyNiCanViewAny::class))->toBeTrue($mensaje);
        expect(str_contains($mensaje, ModeloDePaquete::class))->toBeTrue($mensaje);
    }
});

it('pasa cuando el Resource no tiene policy pero sobreescribe canViewAny() a mano', function (): void {
    Filament::getCurrentOrDefaultPanel()->resources([ResourceSinPolicyConCanViewAnyPropio::class]);

    (new NingunResourceSinAutorizacion)->todoResourceAutorizaDeVerdad();
});

it('no marca en rojo un panel sin Resources', function (): void {
    (new NingunResourceSinAutorizacion)->todoResourceAutorizaDeVerdad();
});

// --- Lista explícita opcional: modelo => policy exacta ---------------------

it('con lista explícita, pasa cuando el modelo resuelve a la policy exacta esperada', function (): void {
    Gate::policy(OtroModeloDePaquete::class, PoliticaDeOtroModeloDePaquete::class);

    (new NingunResourceSinAutorizacion(politicasExactas: [
        OtroModeloDePaquete::class => PoliticaDeOtroModeloDePaquete::class,
    ]))->lasPoliticasExactasEstanRegistradas();
});

it('con lista explícita, detecta un modelo que no resuelve a ninguna policy', function (): void {
    expect(fn () => (new NingunResourceSinAutorizacion(politicasExactas: [
        ModeloDePaquete::class => PoliticaDeOtroModeloDePaquete::class,
    ]))->lasPoliticasExactasEstanRegistradas())
        ->toThrow(AssertionFailedError::class, ModeloDePaquete::class);
});
