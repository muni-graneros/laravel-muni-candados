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
 * Son comprobaciones de FORMA sobre archivos: no ejecutan el worker ni abren
 * un navegador. No reemplazan la prueba en el navegador, evitan que vuelva a
 * aparecer lo que ya costó averiguar una vez.
 */
final class PwaSinRestosDelScaffold extends Candado
{
    public function __construct(
        private readonly ?string $publico = null,
        private readonly ?string $vistas = null,
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
        // Eso ya pasó de verdad en el maestro de personas. La regla es simple:
        // si nadie lo registra, no se sirve.
        $vistas = $this->textoDeLasVistas();

        foreach ($this->archivos('sw*.js') as $ruta) {
            $nombre = basename($ruta);

            Assert::assertStringContainsString(
                $nombre,
                $vistas,
                "se sirve «public/{$nombre}» pero ninguna vista lo registra: ".
                'un service worker huérfano es un cachea-todo esperando a que alguien lo active. '.
                'Bórralo, o regístralo con su scope acotado si de verdad hace falta.'
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

            Assert::assertStringContainsString(
                $nombre,
                $vistas,
                "se sirve «public/{$nombre}» pero ninguna vista lo enlaza: ".
                'declara una identidad que nadie usa y que puede no ser la de este sistema.'
            );
        }
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
