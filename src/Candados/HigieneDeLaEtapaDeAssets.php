<?php

declare(strict_types=1);

namespace Muni\Candados\Candados;

use Muni\Candados\Candado;
use PHPUnit\Framework\Assert;

/**
 * La etapa Node del Dockerfile no instala lo que no va a producción.
 *
 * Los sistemas del ecosistema compilan sus assets en una etapa
 * `FROM node:… AS assets` del Dockerfile. Un `npm ci` a secas ahí dentro hace
 * dos cosas que nadie quiere en la imagen que después corre en el VPS
 * municipal: instala las `devDependencies` y **ejecuta los `postinstall` de
 * terceros durante el build**, que es ejecución de código ajeno dentro de la
 * imagen.
 *
 * No es hipotético. `demo-engine` —la herramienta con la que César graba los
 * videos tutoriales, que nunca corre en el servidor— arrastra `ffmpeg-static`,
 * cuyo `postinstall` **se baja un binario de ~70 MB desde GitHub** mientras se
 * construye la imagen. Eso significa que la imagen de producción no se puede
 * construir si GitHub no responde (ya se vio fallar con un 407 detrás de un
 * proxy), y que entra al build código que ningún archivo del repositorio
 * declara ni verifica.
 *
 * `--omit=dev` deja fuera todo eso, e `--ignore-scripts` es la segunda
 * cerradura: aunque una dependencia de producción gane un `postinstall` en
 * cualquier actualización futura, la imagen no lo ejecuta.
 *
 * **La trampa que este candado existe para evitar**: `--omit=dev` sin más deja
 * al sistema SIN ASSETS, porque en un Laravel recién generado `vite` y
 * `tailwindcss` viven en `devDependencies` por costumbre. Acá esa división
 * significa otra cosa y hay que respetarla: `dependencies` es lo que hace falta
 * para producir `public/build` dentro de la imagen; `devDependencies` es lo que
 * solo se usa en el equipo. Por eso el candado no comprueba solo el flag:
 * comprueba también que la cadena de herramientas esté del lado correcto. Un
 * arreglo que deja el panel sin CSS es peor que el problema que cierra.
 *
 * Nació en `rrhh-graneros`, el primer sistema que lo cerró, y se promovió acá
 * cuando se comprobó que los ocho tienen la misma etapa y solo ese la vigilaba.
 */
final class HigieneDeLaEtapaDeAssets extends Candado
{
    /**
     * @param  string|null  $dockerfile  por omisión `Dockerfile` en la raíz
     * @param  string|null  $packageJson  por omisión `package.json` en la raíz
     * @param  string|null  $packageLock  por omisión `package-lock.json` en la raíz
     * @param  list<string>  $herramientas  lo que la cadena de build necesita sí o sí
     * @param  list<string>  $soloDeEscritorio  paquetes que NO pueden estar en `dependencies`
     * @param  string  $entradasJs  patrón glob de los entrypoints cuyo `import` se mira
     */
    public function __construct(
        private readonly ?string $dockerfile = null,
        private readonly ?string $packageJson = null,
        private readonly ?string $packageLock = null,
        private readonly array $herramientas = ['vite', 'laravel-vite-plugin'],
        private readonly array $soloDeEscritorio = ['demo-engine'],
        private readonly string $entradasJs = 'resources/js/*.js',
    ) {}

    public function registrar(): void
    {
        $candado = $this;

        it('la etapa de assets instala solo lo que necesita el bundle y no ejecuta scripts de terceros', function () use ($candado): void {
            $candado->laEtapaNoInstalaDeMas();
        });

        it('todo lo que necesita `vite build` está en dependencies, porque la imagen instala con --omit=dev', function () use ($candado): void {
            $candado->loQueNecesitaElBundleEstaEnDependencies();
        });

        it('ningún paquete de la instalación de producción se resuelve fuera del registro de npm', function () use ($candado): void {
            $candado->produccionSoloHablaConElRegistroDeNpm();
        });
    }

    public function laEtapaNoInstalaDeMas(): void
    {
        $etapa = $this->etapaDeAssets();

        Assert::assertNotSame(
            '',
            $etapa,
            'el Dockerfile ya no tiene una etapa «FROM node:… AS assets»: si cambió el nombre de la '
            .'etapa, este candado dejó de mirar lo que creía y hay que actualizarlo'
        );

        preg_match_all('/^RUN\s+npm\s+ci\b[^\n]*/mi', $etapa, $instalaciones);

        Assert::assertCount(
            1,
            $instalaciones[0],
            'la etapa de assets tiene '.count($instalaciones[0]).' invocaciones de «npm ci» y debería tener exactamente una'
        );

        $npmCi = trim($instalaciones[0][0]);

        Assert::assertStringContainsString(
            '--omit=dev',
            $npmCi,
            "«{$npmCi}» instala también las devDependencies dentro de la imagen de producción: ahí vive "
            .implode(', ', $this->soloDeEscritorio).', que no corre en el servidor'
        );

        Assert::assertStringContainsString(
            '--ignore-scripts',
            $npmCi,
            "«{$npmCi}» ejecuta los postinstall de terceros durante la construcción de la imagen, que es "
            .'ejecución de código ajeno dentro de lo que después corre en el VPS'
        );
    }

    public function loQueNecesitaElBundleEstaEnDependencies(): void
    {
        $paquete = $this->paquete();

        /** @var array<string, string> $dependencias */
        $dependencias = (array) ($paquete['dependencies'] ?? []);
        $nombres = array_keys($dependencias);

        $faltantes = array_values(array_diff(
            array_unique([...$this->herramientas, ...$this->importadosPorElBundle()]),
            $nombres
        ));

        Assert::assertSame(
            [],
            $faltantes,
            'la imagen instala con --omit=dev, así que esto tiene que estar en «dependencies» de '
            .'package.json y no está: '.implode(', ', $faltantes).'. Sin ellos «vite build» no corre '
            .'dentro de la imagen y el sistema queda sin assets, que es peor que el problema que '
            .'cierra el flag'
        );

        $deEscritorioEnProduccion = array_values(array_intersect($this->soloDeEscritorio, $nombres));

        Assert::assertSame(
            [],
            $deEscritorioEnProduccion,
            'esto solo se usa en el equipo y está en «dependencies», así que --omit=dev NO lo excluye: '
            .implode(', ', $deEscritorioEnProduccion)
        );
    }

    public function produccionSoloHablaConElRegistroDeNpm(): void
    {
        $lock = $this->json($this->packageLock ?? $this->ruta('package-lock.json'), 'el package-lock.json');

        $fueraDelRegistro = [];

        /** @var array<string, array<string, mixed>> $paquetes */
        $paquetes = (array) ($lock['packages'] ?? []);

        foreach ($paquetes as $ruta => $entrada) {
            $entrada = (array) $entrada;

            // La raíz no se instala, y lo marcado como `dev` no entra con --omit=dev.
            if ($ruta === '' || ($entrada['dev'] ?? false) === true || ! isset($entrada['resolved'])) {
                continue;
            }

            if (! str_starts_with((string) $entrada['resolved'], 'https://registry.npmjs.org/')) {
                $fueraDelRegistro[] = $ruta.' ← '.(string) $entrada['resolved'];
            }
        }

        Assert::assertSame(
            [],
            $fueraDelRegistro,
            'la imagen de producción instalaría desde fuera del registro de npm, o sea código que el '
            .'lock no puede verificar contra un registro: '.implode('; ', $fueraDelRegistro)
        );
    }

    /**
     * La etapa `FROM node:… AS assets` del Dockerfile, con las continuaciones de
     * línea ya unidas para que un `npm ci \` partido en dos no se lea como otra cosa.
     */
    private function etapaDeAssets(): string
    {
        $contenido = (string) preg_replace(
            '/\\\\\s*\n\s*/',
            ' ',
            $this->contenidoDe($this->dockerfile ?? $this->ruta('Dockerfile'), 'el Dockerfile')
        );

        preg_match('/^FROM\s+node:[^\n]*\bAS\s+assets\b.*?(?=^FROM\s|\z)/msi', $contenido, $etapa);

        return $etapa[0] ?? '';
    }

    /**
     * Lo que el bundle importa por nombre de paquete.
     *
     * Se miran los entrypoints de JS —los `import … from 'paquete'`, descartando
     * los relativos— y también los `import` del propio `vite.config.js`, que es
     * donde viven los plugins de la cadena de build (`@tailwindcss/vite`,
     * `laravel-vite-plugin`). Derivarlo del archivo y no de una lista escrita a
     * mano es lo que hace que este candado sirva igual en un sistema con Tailwind
     * 3 y postcss que en uno con Tailwind 4 y su plugin de Vite.
     *
     * @return list<string>
     */
    private function importadosPorElBundle(): array
    {
        $raiz = dirname($this->packageJson ?? $this->ruta('package.json'));

        $archivos = (array) glob($raiz.'/'.$this->entradasJs);

        foreach (['vite.config.js', 'vite.config.ts', 'postcss.config.js'] as $config) {
            if (is_file($raiz.'/'.$config)) {
                $archivos[] = $raiz.'/'.$config;
            }
        }

        $importados = [];

        foreach ($archivos as $archivo) {
            $contenido = (string) file_get_contents((string) $archivo);

            // `import x from 'paquete'` y `require('paquete')`, sin los relativos.
            preg_match_all('/(?:^\s*import\b[^\'"\n]*|require\s*\()\s*[\'"]([^.\/][^\'"]*)[\'"]/m', $contenido, $coincidencias);

            foreach ($coincidencias[1] as $especificador) {
                $partes = explode('/', $especificador);

                $importados[] = str_starts_with($especificador, '@')
                    ? $partes[0].'/'.($partes[1] ?? '')
                    : $partes[0];
            }
        }

        return array_values(array_unique($importados));
    }

    /**
     * @return array<string, mixed>
     */
    private function paquete(): array
    {
        return $this->json($this->packageJson ?? $this->ruta('package.json'), 'el package.json');
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $ruta, string $queEs): array
    {
        $decodificado = json_decode($this->contenidoDe($ruta, $queEs), true);

        if (! is_array($decodificado)) {
            Assert::fail($queEs.' no es JSON válido');
        }

        /** @var array<string, mixed> $decodificado */
        return $decodificado;
    }
}
