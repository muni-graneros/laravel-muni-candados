<?php

declare(strict_types=1);

namespace Muni\Candados\Candados;

use Muni\Candados\Candado;
use PHPUnit\Framework\Assert;

/**
 * Candado de la imagen que se publica y corre en el VPS municipal.
 *
 * Son comprobaciones de FORMA sobre el Dockerfile: no reemplazan construir la
 * imagen, evitan que se pierda lo que ya costó averiguar una vez. No necesitan
 * levantar la aplicación ni la base —miran archivos de construcción—, así que
 * se pueden registrar también desde `tests/Unit`.
 */
final class ImagenDeProduccion extends Candado
{
    public function __construct(
        private readonly ?string $dockerfile = null,
        private readonly string $imagenBase = 'dunglas/frankenphp',
    ) {}

    public function registrar(): void
    {
        $candado = $this;

        it('la imagen base de FrankenPHP está fijada a una versión concreta', function () use ($candado): void {
            $candado->laImagenBaseEstaFijadaAUnaVersionConcreta();
        });

        it('la imagen sabe decir si está sana, y lo dice con una shell', function () use ($candado): void {
            $candado->laImagenSabeDecirSiEstaSanaYLoDiceConUnaShell();
        });

        it('la imagen trae la herramienta con la que se comprueba a sí misma', function () use ($candado): void {
            $candado->laImagenTraeLaHerramientaConLaQueSeCompruebaASiMisma();
        });
    }

    public function laImagenBaseEstaFijadaAUnaVersionConcreta(): void
    {
        // `dunglas/frankenphp:1-php8.5-alpine` es una etiqueta que se MUEVE: el
        // mantenedor la reapunta cada vez que publica una versión menor. Dos
        // construcciones del mismo commit dan entonces imágenes distintas, y basta
        // un rebuild del CI —una dependencia actualizada, un reintento— para que el
        // servidor que atiende a los vecinos se lleve un FrankenPHP que nadie
        // eligió y que ningún commit explica. Si esa versión trae una regresión,
        // tampoco hay a qué volver: la etiqueta vieja ya apunta a otra imagen.
        //
        // Se sube a propósito, cambiando el número en el Dockerfile, en su propio
        // commit y construyendo la imagen para probarla.
        $patron = '/^FROM\s+'.preg_quote($this->imagenBase, '/').':(\S+)/mi';
        preg_match_all($patron, $this->dockerfile(), $etapas);

        $etiquetas = $etapas[1] ?? [];

        Assert::assertNotEmpty($etiquetas, "el Dockerfile ya no parte de una imagen de {$this->imagenBase}");

        foreach ($etiquetas as $etiqueta) {
            Assert::assertMatchesRegularExpression(
                '/^\d+\.\d+\.\d+-php\d+\.\d+/',
                $etiqueta,
                "«{$etiqueta}» es una etiqueta flotante: fija la versión completa (p. ej. 1.12.7-php8.5-alpine)"
            );
        }
    }

    public function laImagenSabeDecirSiEstaSanaYLoDiceConUnaShell(): void
    {
        // La imagen base NO viene sin chequeo, y ahí está la trampa:
        // `dunglas/frankenphp` declara el suyo contra `localhost:2019/metrics`, la
        // API admin de Caddy. Ese puerto lo abre Caddy y devuelve 200 aunque Octane
        // haya dejado de atender en el 8000, así que el contenedor se declaraba sano
        // con la aplicación caída: el despliegue lo aceptaba y el reinicio
        // automático nunca se disparaba. El primero en enterarse era el vecino que
        // abría la página. Por eso el Dockerfile tiene que SOBREESCRIBIR el chequeo
        // heredado; borrarlo no deja la imagen sin chequeo, la deja con el que
        // miente en verde.
        $instruccion = [];
        preg_match('/^HEALTHCHECK[^\n]*/mi', $this->dockerfileEnUnaLinea(), $instruccion);

        Assert::assertNotEmpty($instruccion, 'la imagen de producción no declara HEALTHCHECK');

        $healthcheck = $instruccion[0] ?? '';

        // Forma exec (`CMD ["curl", …]`): ahí NO hay shell, así que el `|| exit 1`
        // viajaría como dos argumentos más de curl en vez de decidir el resultado,
        // y el contenedor quedaría sano pasara lo que pasara.
        Assert::assertDoesNotMatchRegularExpression(
            '/CMD\s*\[/',
            $healthcheck,
            'el HEALTHCHECK usa la forma exec: sin shell, el «|| exit 1» no hace nada'
        );

        // Y que no sea el chequeo heredado: `localhost:2019/metrics` responde 200
        // mientras Caddy esté vivo, aunque Octane no atienda ni una petición.
        Assert::assertDoesNotMatchRegularExpression(
            '/:2019/',
            $healthcheck,
            'el HEALTHCHECK volvió a comprobar la API admin de Caddy: responde 200 aunque Octane no atienda'
        );

        // Contra `/up`, la ruta de salud que declara bootstrap/app.php: responde sin
        // tocar la base ni la cola, así que un corte momentáneo de MariaDB no hace
        // que Docker mate y reinicie la aplicación en bucle.
        Assert::assertMatchesRegularExpression(
            '#/up#',
            $healthcheck,
            'el HEALTHCHECK no apunta a la ruta de salud declarada en bootstrap/app.php'
        );
    }

    public function laImagenTraeLaHerramientaConLaQueSeCompruebaASiMisma(): void
    {
        // El chequeo se hace con curl. La base de FrankenPHP hoy lo trae —su propio
        // chequeo heredado lo usaba—, pero se declara igual en el `apk add` para que
        // el chequeo no dependa de eso: si una versión futura de la base dejara de
        // incluirlo, el chequeo fallaría siempre y el contenedor quedaría
        // «unhealthy» con la aplicación sana, que es peor que no tener chequeo
        // porque enseña a ignorarlo.
        Assert::assertMatchesRegularExpression(
            '/apk add[^\n]*\bcurl\b/',
            $this->dockerfileEnUnaLinea(),
            'el HEALTHCHECK usa curl y la imagen no lo instala'
        );
    }

    private function dockerfile(): string
    {
        return $this->contenidoDe($this->dockerfile ?? $this->ruta('Dockerfile'), 'el Dockerfile');
    }

    /**
     * Une las continuaciones de línea («\» al final) para poder buscar una
     * instrucción completa aunque esté partida en varias por legibilidad.
     */
    private function dockerfileEnUnaLinea(): string
    {
        return (string) preg_replace('/\\\\\s*\n\s*/', ' ', $this->dockerfile());
    }
}
