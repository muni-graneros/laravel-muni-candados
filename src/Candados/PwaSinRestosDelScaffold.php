<?php

declare(strict_types=1);

namespace Muni\Candados\Candados;

use Muni\Candados\Candado;
use PHPUnit\Framework\Assert;

/**
 * Candado de los artefactos de PWA que se sirven desde `public/`.
 *
 * Un service worker es el único código del sistema que sigue respondiendo
 * cuando el servidor no está: si guarda una página autenticada, la sirve
 * después sin sesión, sin cookie y sin pasar por ninguna policy. Por eso los
 * archivos de PWA no pueden quedar sueltos «por si acaso».
 *
 * Un archivo huérfano —que ninguna vista registra ni enlaza— tiene DOS
 * orígenes muy distintos, y confundirlos es el defecto que este candado
 * arregla:
 *
 *  - **Resto de andamio**: el scaffold reparte un `sw.js` y un
 *    `manifest.webmanifest` genéricos que nadie personalizó. Corresponde
 *    borrarlos.
 *  - **Función construida a la que le falta activarse**: un sistema real
 *    escribió su propio worker —con su test dedicado, a veces con
 *    auditorías de seguridad encima— y solo falta la línea de
 *    `serviceWorker.register()` o el `<link rel="manifest">`. Pasó de
 *    verdad con la PWA de terreno de seguridad-graneros (GPS, cola offline,
 *    botón de pánico, con `tests/Feature/PwaPatrulleroTest.php` encima):
 *    este candado la marcaba en rojo con el mismo «Bórralo» que usa para el
 *    sobrante del scaffold. Empujar a borrar trabajo probado es peor que no
 *    tener candado.
 *
 * La distinción se hace con evidencia, no por el nombre del archivo:
 *
 *  1. Si el sistema declaró una EXCEPCIÓN explícita para ese archivo, con su
 *     motivo escrito, no se toca: ya decidió una persona.
 *  2. Si el archivo calza BYTE A BYTE con el que reparte el scaffold (mismo
 *     SHA-256), es ese resto con certeza: el mensaje dice que se borra.
 *  3. Si no calza pero existe una prueba dedicada en `tests/` que lo
 *     ejercita, es función construida: el mensaje dice que falta el
 *     registro y no sugiere borrar nada.
 *  4. Sin evidencia en ningún sentido, el mensaje no empuja a ninguna de las
 *     dos acciones: pide revisión humana y, si es intencional, la excepción
 *     con su motivo.
 *
 * Son comprobaciones de FORMA sobre archivos: no ejecutan el worker ni abren
 * un navegador. No reemplazan la prueba en el navegador, evitan que vuelva a
 * aparecer lo que ya costó averiguar una vez.
 */
final class PwaSinRestosDelScaffold extends Candado
{
    /**
     * SHA-256 de `public/sw.js` y `public/manifest.webmanifest` tal como los
     * repartía `scaffold-laravel-filament-pwa` antes del commit `7f7e2db`
     * («quitar la PWA fantasma»), que los borró por huérfanos. Un archivo
     * que calza con uno de estos hashes es ESE resto, con certeza y no por
     * su nombre: nadie le cambió ni un byte desde que el scaffold lo generó.
     */
    private const string HASH_SW_DEL_SCAFFOLD = '6196d49715f7cb22257ee8fa8d5d689aa88888059ef320a4e5e168d3d308eb43';

    private const string HASH_MANIFEST_DEL_SCAFFOLD = '5dca3b7ca09b07b0a6d689308a1e87324e5d60bd9ec6c4b667abc5b5aef6236c';

    /**
     * @param  string|null  $publico  por omisión `public`
     * @param  string|null  $vistas  por omisión `resources/views`
     * @param  string|null  $tests  por omisión `tests`; dónde se busca una prueba dedicada al archivo antes de decidir que un huérfano es función construida
     * @param  array<string, string>  $excepciones  nombre de archivo (p. ej. `sw.js`) => motivo por el que se lo exime, escrito por una persona
     */
    public function __construct(
        private readonly ?string $publico = null,
        private readonly ?string $vistas = null,
        private readonly ?string $tests = null,
        private readonly array $excepciones = [],
    ) {}

    public function registrar(): void
    {
        $candado = $this;

        it('no se sirve ningún service worker que nadie registre', function () use ($candado): void {
            $candado->noSeSirveNingunServiceWorkerQueNadieRegistre();
        });

        it('no se sirve ningún manifest que nadie enlace', function () use ($candado): void {
            $candado->noSeSirveNingunManifestQueNadieEnlace();
        });

        it('cada service worker registrado existe en public/', function () use ($candado): void {
            $candado->cadaServiceWorkerRegistradoExiste();
        });
    }

    public function noSeSirveNingunServiceWorkerQueNadieRegistre(): void
    {
        // El scaffold repartió un `public/sw.js` que cachea toda respuesta GET
        // con estado 200, sin mirar la ruta ni la autenticación. Mientras nadie
        // lo registra es inerte, y por eso sobrevivió en ocho repos: no rompe
        // nada, no aparece en ninguna revisión. Basta que alguien copie las dos
        // líneas de `serviceWorker.register` de otro sistema «para hacerla
        // instalable» y el panel entero empieza a escribirse en el disco del
        // equipo, donde queda tras cerrar sesión y lo lee el turno siguiente.
        //
        // Eso ya pasó de verdad en el maestro de personas. Pero un huérfano
        // también puede ser lo contrario: código propio al que solo le falta
        // esa línea de registro (ver `evaluarArchivoSinUso`).
        $vistas = $this->textoDeLasVistas();

        foreach ($this->archivos('sw*.js') as $ruta) {
            $nombre = basename($ruta);

            $this->evaluarArchivoSinUso(
                ruta: $ruta,
                nombre: $nombre,
                vistas: $vistas,
                verbo: 'lo registra',
                comoActivarlo: "navigator.serviceWorker.register('/{$nombre}')",
                hashDeAndamio: self::HASH_SW_DEL_SCAFFOLD,
            );
        }
    }

    public function noSeSirveNingunManifestQueNadieEnlace(): void
    {
        // Un manifest huérfano no filtra datos, pero se sirve sin autenticación
        // y declara una identidad: el del scaffold decía «Sistema Municipal
        // Boilerplate» con `start_url` a una ruta inexistente. Cualquiera podía
        // pedirlo y leer una afirmación equivocada sobre qué es este sistema.
        $vistas = $this->textoDeLasVistas();

        foreach ($this->archivos('manifest*.webmanifest') as $ruta) {
            $nombre = basename($ruta);

            $this->evaluarArchivoSinUso(
                ruta: $ruta,
                nombre: $nombre,
                vistas: $vistas,
                verbo: 'lo enlaza',
                comoActivarlo: "<link rel=\"manifest\" href=\"/{$nombre}\">",
                hashDeAndamio: self::HASH_MANIFEST_DEL_SCAFFOLD,
            );
        }
    }

    /**
     * El corazón del candado: qué hacer con un archivo que ninguna vista usa.
     *
     * El orden importa. Una excepción declarada corta todo lo demás porque ya
     * la decidió una persona con nombre y motivo. Después, el hash: es la
     * única señal que da CERTEZA (no probabilidad) de que es el resto del
     * scaffold, así que se mira antes que la prueba dedicada — un sistema
     * podría (por accidente) tener un test que menciona el nombre de un
     * archivo que en los hechos es el sobrante sin tocar. Sin hash y sin
     * prueba dedicada, no hay evidencia para ninguno de los dos mensajes, y
     * empujar a cualquiera de las dos acciones sin evidencia es el defecto
     * original.
     */
    private function evaluarArchivoSinUso(
        string $ruta,
        string $nombre,
        string $vistas,
        string $verbo,
        string $comoActivarlo,
        string $hashDeAndamio,
    ): void {
        if (str_contains($vistas, $nombre)) {
            // Assert real (no un `assertTrue(true)` vacío): confirma que el
            // archivo servido es el que alguna vista de verdad usa.
            Assert::assertStringContainsString($nombre, $vistas);

            return;
        }

        $motivo = $this->excepciones[$nombre] ?? null;

        if ($motivo !== null) {
            Assert::assertNotSame(
                '',
                trim($motivo),
                "la excepción declarada para «{$nombre}» no trae motivo: una excepción sin razón escrita ".
                'es un archivo del que en seis meses nadie recuerda por qué está exento. '.
                "Escribí por qué en excepciones['{$nombre}']."
            );

            // Una persona ya miró este archivo y decidió eximirlo con un motivo
            // escrito: no hay nada más que este candado deba decidir por ella.
            return;
        }

        $prefijo = "se sirve «public/{$nombre}» pero ninguna vista {$verbo}: ";

        if (hash_equals($hashDeAndamio, (string) hash_file('sha256', $ruta))) {
            Assert::fail(
                $prefijo.
                'es BYTE A BYTE el que reparte el scaffold sin personalizar (mismo SHA-256 que el original): '.
                'es un resto de andamio, no código construido. Bórralo, o personalizalo y registralo '.
                'con su scope acotado si de verdad hace falta.'
            );
        }

        if ($this->tieneTestDedicado($nombre)) {
            Assert::fail(
                $prefijo.
                'y no es el archivo del scaffold: hay una prueba dedicada en tests/ que lo ejercita, así que '.
                'es función construida a la que le falta activarse. No lo borres: registralo con '.
                "{$comoActivarlo} en la vista que corresponda, con su scope acotado."
            );
        }

        Assert::fail(
            $prefijo.
            'no calza con el archivo que reparte el scaffold y no hay una prueba dedicada que lo cubra: '.
            'no hay evidencia para decidir solo si es un resto o una función a medio activar. Revisalo a mano, '.
            'y si es intencional declará la excepción con su motivo '.
            '(Candados::pwaSinRestosDelScaffold(excepciones: [...])).'
        );
    }

    public function cadaServiceWorkerRegistradoExiste(): void
    {
        // El reverso: una vista que registra un archivo que no está deja la app
        // sin funcionamiento offline y con un 404 silencioso, porque el
        // `.catch(() => {})` del registro se traga el error.
        preg_match_all(
            "#serviceWorker\.register\(\s*['\"](/[^'\"]+)['\"]#",
            $this->textoDeLasVistas(),
            $encontrados
        );

        foreach (array_unique($encontrados[1]) as $registrado) {
            Assert::assertFileExists(
                $this->rutaPublica(ltrim($registrado, '/')),
                "una vista registra «{$registrado}» y ese archivo no existe en public/: ".
                'el registro falla en silencio y la app no funciona sin red.'
            );
        }
    }

    /**
     * Los archivos de `public/` que calzan con un patrón.
     *
     * @return list<string>
     */
    private function archivos(string $patron): array
    {
        return glob($this->rutaPublica($patron)) ?: [];
    }

    private function rutaPublica(string $relativa = ''): string
    {
        $base = $this->publico ?? $this->ruta('public');

        return rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($relativa, DIRECTORY_SEPARATOR);
    }

    /**
     * Si existe, en algún lugar de `tests/`, un archivo `.php` que menciona
     * este nombre de archivo.
     *
     * No exige que el nombre del método de test lo diga, ni que sea una
     * prueba de Feature: alcanza con que el sistema haya escrito algo que lo
     * ejercite (`public_path('sw.js')`, un `assertFileExists`, lo que sea).
     * Es una señal, no una prueba matemática — por eso se mira DESPUÉS del
     * hash del scaffold, que sí da certeza. Si el directorio de tests no
     * existe, no hay señal: se devuelve `false`, nunca se falla por esto.
     */
    private function tieneTestDedicado(string $nombre): bool
    {
        $raiz = $this->tests ?? $this->ruta('tests');

        if (! is_dir($raiz)) {
            return false;
        }

        $archivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($archivos as $archivo) {
            if (! $archivo->isFile() || ! str_ends_with($archivo->getFilename(), '.php')) {
                continue;
            }

            if (str_contains((string) file_get_contents($archivo->getPathname()), $nombre)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Todas las vistas del proyecto concatenadas.
     *
     * Se busca sobre el texto entero y no vista por vista a propósito: da igual
     * cuál registre el worker, lo que importa es que alguna lo haga.
     */
    private function textoDeLasVistas(): string
    {
        $raiz = $this->vistas ?? $this->ruta('resources/views');

        if (! is_dir($raiz)) {
            Assert::fail("no existe el directorio de vistas en «{$raiz}»");
        }

        $texto = '';

        $archivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($archivos as $archivo) {
            if ($archivo->isFile() && str_ends_with($archivo->getFilename(), '.blade.php')) {
                $texto .= (string) file_get_contents($archivo->getPathname())."\n";
            }
        }

        return $texto;
    }
}
