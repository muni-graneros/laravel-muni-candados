<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\FontProviders\BunnyFontProvider;
use Filament\FontProviders\LocalFontProvider;
use Illuminate\Support\Facades\Route;
use Muni\Candados\Candados\SinCdnDeFuentesNiIconos;
use PHPUnit\Framework\AssertionFailedError;

$fixtures = dirname(__DIR__).'/Fixtures';

// --- Tipografía del panel -------------------------------------------------
//
// El panel de la fixture Cumple (PanelDePruebaProvider) no llama a ->font():
// es el arreglo real de rrhh, seguridad, discapacidad, control-acceso y web
// -«borrar la línea»-, y Filament resuelve Inter self-hosted por su cuenta.

it('pasa cuando el panel no llama a ->font(): Filament resuelve Inter self-hosted solo', function (): void {
    (new SinCdnDeFuentesNiIconos)->laTipografiaDelPanelUsaElProveedorLocal();
});

it('detecta que ->font() sin provider cae a BunnyFontProvider', function (): void {
    // El bug real, tal como estaba en control-acceso antes del arreglo.
    Filament::getCurrentOrDefaultPanel()->font('Inter');

    expect(fn () => (new SinCdnDeFuentesNiIconos)->laTipografiaDelPanelUsaElProveedorLocal())
        ->toThrow(AssertionFailedError::class, BunnyFontProvider::class);
});

it('pasa cuando ->font() trae un provider explícito, para una familia que Filament no self-hostea', function (): void {
    // El otro arreglo legítimo: feria-graneros con IBM Plex Sans, self-hosteada
    // con @fontsource y provider: LocalFontProvider::class explícito.
    Filament::getCurrentOrDefaultPanel()->font('IBM Plex Sans', provider: LocalFontProvider::class);

    (new SinCdnDeFuentesNiIconos)->laTipografiaDelPanelUsaElProveedorLocal();
});

it('falla claro si Filament no está instalado', function (): void {
    // No se puede desinstalar Filament en este proceso: se prueba pasando un
    // idDelPanel que no existe en ningún panel registrado, que es el mismo
    // camino de falla («no hay ningún panel»).
    expect(fn () => (new SinCdnDeFuentesNiIconos(idDelPanel: 'no-existe'))->laTipografiaDelPanelUsaElProveedorLocal())
        ->toThrow(AssertionFailedError::class, 'no hay ningún panel de Filament registrado');
});

// --- CSP -------------------------------------------------------------------
//
// Comportamiento real: se pide una ruta y se lee la cabecera que la
// aplicación realmente devuelve. Nada de leer el middleware por su código:
// lo que importa es lo que le llega al navegador.

it('pasa con una CSP que no nombra ningún host prohibido', function (): void {
    Route::get('/__prueba-fuentes-csp-limpia', fn () => response('ok')->header(
        'Content-Security-Policy',
        "default-src 'self'; font-src 'self'; style-src 'self' 'unsafe-inline'"
    ));

    (new SinCdnDeFuentesNiIconos(ruta: '/__prueba-fuentes-csp-limpia'))
        ->laCspNoNombraNingunHostDeFuentesNiIconosDeTerceros();
});

it('detecta cada host prohibido en la CSP, nombrándolo en el mensaje', function (string $host): void {
    Route::get('/__prueba-fuentes-csp-mala', fn () => response('ok')->header(
        'Content-Security-Policy',
        "default-src 'self'; font-src 'self' https://{$host}"
    ));

    expect(fn () => (new SinCdnDeFuentesNiIconos(ruta: '/__prueba-fuentes-csp-mala'))
        ->laCspNoNombraNingunHostDeFuentesNiIconosDeTerceros())
        ->toThrow(AssertionFailedError::class, $host);
})->with([
    'fonts.bunny.net',
    'fonts.googleapis.com',
    'fonts.gstatic.com',
    'cdnjs.cloudflare.com',
]);

it('lee la CSP en Report-Only cuando no hay una de bloqueo', function (): void {
    // Varios sistemas del ecosistema corren la CSP en Report-Only fuera de
    // producción (config/seguridad.php): misma política, solo cambia si
    // bloquea o solo avisa.
    Route::get('/__prueba-fuentes-csp-report-only', fn () => response('ok')->header(
        'Content-Security-Policy-Report-Only',
        "font-src 'self' https://fonts.bunny.net"
    ));

    expect(fn () => (new SinCdnDeFuentesNiIconos(ruta: '/__prueba-fuentes-csp-report-only'))
        ->laCspNoNombraNingunHostDeFuentesNiIconosDeTerceros())
        ->toThrow(AssertionFailedError::class, 'fonts.bunny.net');
});

it('falla claro si la respuesta no trae ninguna CSP', function (): void {
    Route::get('/__prueba-fuentes-sin-csp', fn () => 'ok');

    expect(fn () => (new SinCdnDeFuentesNiIconos(ruta: '/__prueba-fuentes-sin-csp'))
        ->laCspNoNombraNingunHostDeFuentesNiIconosDeTerceros())
        ->toThrow(AssertionFailedError::class, 'no trae ninguna Content-Security-Policy');
});

// --- Archivos de resources/ -------------------------------------------------

it('pasa cuando resources/ no referencia ningún host prohibido', function () use ($fixtures): void {
    (new SinCdnDeFuentesNiIconos(recursos: $fixtures.'/Cumple/resources'))
        ->ningunArchivoDeRecursosReferenciaUnHostDeFuentesNiIconosDeTerceros();
});

it('no cuenta la mención en un comentario que explica que ya no se usa', function () use ($fixtures): void {
    // tests/Fixtures/Cumple/resources/css/panel-fuente.css nombra
    // «fonts.bunny.net» sin esquema, en un comentario: no es una URL de
    // verdad, y esta es la misma prueba que la de arriba, dejada explícita.
    $contenido = file_get_contents($fixtures.'/Cumple/resources/css/panel-fuente.css');

    expect(str_contains((string) $contenido, 'fonts.bunny.net'))->toBeTrue('la fixture no prueba nada si no menciona el host');

    (new SinCdnDeFuentesNiIconos(recursos: $fixtures.'/Cumple/resources'))
        ->ningunArchivoDeRecursosReferenciaUnHostDeFuentesNiIconosDeTerceros();
});

it('detecta cada host cargado de verdad en resources/, con el archivo en el mensaje', function () use ($fixtures): void {
    try {
        (new SinCdnDeFuentesNiIconos(recursos: $fixtures.'/NoCumple/resources'))
            ->ningunArchivoDeRecursosReferenciaUnHostDeFuentesNiIconosDeTerceros();

        test()->fail('el candado no detectó ningún host prohibido en resources/');
    } catch (AssertionFailedError $falla) {
        $mensaje = $falla->getMessage();

        expect(str_contains($mensaje, 'perfil.blade.php → fonts.bunny.net'))->toBeTrue($mensaje);
        expect(str_contains($mensaje, 'perfil.blade.php → fonts.googleapis.com'))->toBeTrue($mensaje);
        expect(str_contains($mensaje, 'perfil.blade.php → fonts.gstatic.com'))->toBeTrue($mensaje);
        expect(str_contains($mensaje, 'perfil.blade.php → cdnjs.cloudflare.com'))->toBeTrue($mensaje);
    }
});

it('falla claro si no existe el directorio de recursos', function () use ($fixtures): void {
    $candado = new SinCdnDeFuentesNiIconos(recursos: $fixtures.'/Cumple/no-existe');

    expect(fn () => $candado->ningunArchivoDeRecursosReferenciaUnHostDeFuentesNiIconosDeTerceros())
        ->toThrow(AssertionFailedError::class, 'no existe el directorio de recursos');
});
