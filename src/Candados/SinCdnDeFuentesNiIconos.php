<?php

declare(strict_types=1);

namespace Muni\Candados\Candados;

use Filament\Facades\Filament;
use Filament\FontProviders\LocalFontProvider;
use Filament\Panel;
use Muni\Candados\Candado;
use PHPUnit\Framework\Assert;

/**
 * Candado: ni la tipografía del panel, ni su CSP, ni ningún archivo de
 * `resources/` salen a buscar una fuente o un icono a un CDN de terceros.
 *
 * `->font('Inter')` —o cualquier familia— SIN el argumento `provider:` hace
 * que `HasFont::getFontProvider()` caiga a `BunnyFontProvider` en cuanto hay
 * una familia custom sin proveedor propio (Filament,
 * `Panel/Concerns/HasFont.php`): el panel pide `fonts.bunny.net` en CADA
 * carga, y esa petición le entrega la IP de cada funcionario a un tercero
 * (Ley 21.719), además de dejar el panel sin tipografía en la LAN municipal
 * filtrada o sin salida a internet.
 *
 * Hay DOS arreglos legítimos, y los dos tienen que pasar este candado:
 * - Si la familia es Inter, se BORRA la línea `->font()`: sin ella, Filament
 *   resuelve «Inter Variable» self-hosted con `LocalFontProvider` por su
 *   cuenta. Así quedó en rrhh, seguridad, discapacidad, control-acceso y web.
 * - Si la familia NO viene con Filament (IBM Plex Sans en feria-graneros), se
 *   self-hostea con `@fontsource` y se pasa `provider: LocalFontProvider::class`
 *   explícito. Ver el gotcha de Octane sobre el `url:` de esa llamada en el
 *   README del paquete: no lo puede comprobar un test de forma, así que no
 *   está acá.
 *
 * La CSP y los archivos de `resources/` se vigilan aparte porque son la MISMA
 * fuga por otra puerta: antes del arreglo, `fonts.googleapis.com`,
 * `fonts.gstatic.com` y `cdnjs.cloudflare.com` aparecían sueltos en vistas,
 * hojas de estilo y la propia política de contenido, sirviendo OTRAS
 * tipografías e iconos (Outfit, Font Awesome) fuera del panel de Filament.
 */
final class SinCdnDeFuentesNiIconos extends Candado
{
    /**
     * Los anfitriones que un sistema del ecosistema no tiene por qué pedirle
     * a nadie: todo lo que sirven se self-hostea.
     */
    private const array HOSTS_PROHIBIDOS = [
        'fonts.bunny.net',
        'fonts.googleapis.com',
        'fonts.gstatic.com',
        'cdnjs.cloudflare.com',
    ];

    /**
     * @param  string|null  $idDelPanel  por omisión, el panel actual o el que declaró `->default()`
     * @param  string|null  $ruta  la que se pide para leer la CSP; por omisión, la de acceso del panel
     * @param  string|null  $recursos  por omisión `resources/`
     */
    public function __construct(
        private readonly ?string $idDelPanel = null,
        private readonly ?string $ruta = null,
        private readonly ?string $recursos = null,
    ) {}

    public function registrar(): void
    {
        $candado = $this;

        it('el panel Filament resuelve su tipografía con el proveedor local, no con Bunny', function () use ($candado): void {
            $candado->laTipografiaDelPanelUsaElProveedorLocal();
        });

        it('la CSP no nombra ningún host de fuentes o iconos de terceros', function () use ($candado): void {
            $candado->laCspNoNombraNingunHostDeFuentesNiIconosDeTerceros();
        });

        it('ningún archivo de resources/ referencia un host de fuentes o iconos de terceros', function () use ($candado): void {
            $candado->ningunArchivoDeRecursosReferenciaUnHostDeFuentesNiIconosDeTerceros();
        });
    }

    public function laTipografiaDelPanelUsaElProveedorLocal(): void
    {
        $panel = $this->panelDeFilament();
        $proveedor = $panel->getFontProvider();

        Assert::assertSame(
            LocalFontProvider::class,
            $proveedor,
            "el panel «{$panel->getId()}» resuelve su tipografía («{$panel->getFontFamily()}») con {$proveedor}, "
            .'y no con '.LocalFontProvider::class.': cada carga del panel le pide la fuente a un tercero y le '
            .'entrega su IP (Ley 21.719). Si la familia es Inter, borrá la línea ->font(); si no, self-hosteala '
            .'con @fontsource y pasale provider: '.LocalFontProvider::class.'::class'
        );
    }

    public function laCspNoNombraNingunHostDeFuentesNiIconosDeTerceros(): void
    {
        $ruta = $this->rutaAPedir();
        $respuesta = $this->caso()->get($ruta);

        $csp = (string) $respuesta->headers->get('Content-Security-Policy');

        if ($csp === '') {
            // Fuera de producción varios sistemas del ecosistema corren la CSP
            // en Report-Only (config/seguridad.php): la regla es la misma
            // política, solo cambia si bloquea o solo avisa.
            $csp = (string) $respuesta->headers->get('Content-Security-Policy-Report-Only');
        }

        Assert::assertNotEmpty(
            $csp,
            "la respuesta de «{$ruta}» no trae ninguna Content-Security-Policy (ni de bloqueo ni de "
            .'Report-Only): sin ella, esta comprobación no protege nada'
        );

        foreach (self::HOSTS_PROHIBIDOS as $host) {
            Assert::assertStringNotContainsString(
                $host,
                $csp,
                "la CSP de «{$ruta}» todavía nombra a «{$host}»: es un CDN de terceros para fuentes o iconos, "
                .'y ya no hace falta abrirle una excepción porque todo se sirve self-hosted'
            );
        }
    }

    public function ningunArchivoDeRecursosReferenciaUnHostDeFuentesNiIconosDeTerceros(): void
    {
        $raiz = $this->recursos ?? $this->ruta('resources');

        if (! is_dir($raiz)) {
            Assert::fail("no existe el directorio de recursos en «{$raiz}»");
        }

        $hallazgos = [];

        $archivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($archivos as $archivo) {
            if (! $archivo->isFile() || str_contains($archivo->getPathname(), 'node_modules')) {
                continue;
            }

            $contenido = (string) file_get_contents($archivo->getPathname());

            foreach (self::HOSTS_PROHIBIDOS as $host) {
                // Cuenta una URL de verdad —con esquema o protocolo-relativa,
                // como la carga un <link>, un @import o un <script src>—, no
                // una mención suelta del nombre del host en un comentario que
                // explica que ya no se usa (así lo documentan panel-fuente.css
                // y profile.css en los sistemas que ya se arreglaron).
                $patron = '#(?:https?:)?//'.preg_quote($host, '#').'(?:[/"\'\)\s]|$)#';

                if (preg_match($patron, $contenido) === 1) {
                    $hallazgos[] = $archivo->getFilename().' → '.$host;
                }
            }
        }

        Assert::assertSame(
            [],
            $hallazgos,
            "algún archivo de resources/ todavía carga un host de fuentes o iconos de terceros:\n".implode("\n", $hallazgos)
        );
    }

    private function rutaAPedir(): string
    {
        return $this->ruta ?? (string) $this->panelDeFilament()->getLoginUrl();
    }

    /**
     * Se le pregunta a Filament en vez de escribir la dirección: no todos los
     * paneles del ecosistema se llaman «admin».
     */
    private function panelDeFilament(): Panel
    {
        if (! class_exists(Filament::class)) {
            Assert::fail('sin Filament instalado hay que pasarle «ruta» al candado para la CSP; la comprobación de tipografía no aplica');
        }

        $panel = $this->idDelPanel !== null
            ? Filament::getPanel($this->idDelPanel)
            : Filament::getCurrentOrDefaultPanel();

        if ($panel === null) {
            Assert::fail('no hay ningún panel de Filament registrado: pasale idDelPanel o ruta al candado');
        }

        return $panel;
    }
}
