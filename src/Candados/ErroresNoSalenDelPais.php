<?php

declare(strict_types=1);

namespace Muni\Candados\Candados;

use Muni\Candados\Candado;
use PHPUnit\Framework\Assert;

/**
 * Las trazas de excepción no pueden irse a un servicio en el extranjero.
 *
 * `bootstrap/app.php` enganchaba Sentry con `if (class_exists(Integration::class))`
 * bajo un comentario que decía «si hay DSN configurado». No es lo que comprueba:
 * la clase existe siempre —el paquete está en `composer.json`—, así que el
 * enganche era incondicional y lo único que decidía a dónde iban los errores era
 * el valor de `SENTRY_LARAVEL_DSN` en el `.env`.
 *
 * Una traza de un sistema municipal lleva la ruta, la consulta, a veces el cuerpo
 * del request: eso incluye datos de un vecino. Mandarlos a sentry.io es una
 * transferencia internacional de datos personales, que la Ley 21.719 no permite
 * sin base de licitud: no es un detalle de configuración, y no puede depender de
 * que nadie pegue por error un DSN de la nube en un `.env`.
 *
 * La decisión del ecosistema ya estaba tomada —GlitchTip autoalojado, Sentry
 * descartado— pero vivía en un documento de diseño. Con este candado pasa a
 * estar en el código de cada sistema.
 *
 * El sistema pone la clase que decide (`App\Support\ReporteDeErrores` en los
 * generados desde el scaffold); el candado la ejercita con DSN de un lado y del
 * otro, y vigila que `bootstrap/app.php` la consulte de verdad.
 */
final class ErroresNoSalenDelPais extends Candado
{
    /**
     * @var list<string>
     */
    public const array DSN_AJENOS = [
        'https://clave@o123456.ingest.sentry.io/4501',
        'https://clave@o123456.ingest.us.sentry.io/4501',
        'https://clave@sentry.io/4501',
        // Un subdominio que TERMINA en el dominio ajeno, no que lo contiene: el
        // chequeo tiene que mirar el host, no hacer un str_contains.
        'https://clave@errores.sentry.io/4501',
    ];

    /**
     * @var list<string>
     */
    public const array DSN_PROPIOS = [
        'https://clave@errores.graneros.cl/1',
        'https://clave@127.0.0.1:8400/1',
        'https://clave@glitchtip-web:8000/1',
    ];

    /**
     * @var list<string>
     */
    public const array DSN_ILEGIBLES = [
        'esto-no-es-una-url',
        '   ',
        'https://',
    ];

    /**
     * @param  list<string>|null  $dsnAjenos
     * @param  list<string>|null  $dsnPropios
     * @param  list<string>|null  $dsnIlegibles
     */
    public function __construct(
        private readonly string $clase = 'App\\Support\\ReporteDeErrores',
        private readonly string $metodo = 'vaADestinoPropio',
        private readonly ?string $bootstrap = null,
        private readonly ?array $dsnAjenos = null,
        private readonly ?array $dsnPropios = null,
        private readonly ?array $dsnIlegibles = null,
    ) {}

    public function registrar(): void
    {
        $candado = $this;

        it('sin DSN no se engancha nada', function () use ($candado): void {
            $candado->sinDsnNoSeEnganchaNada();
        });

        it('rechaza la nube de Sentry, que está fuera de Chile', function (string $dsn) use ($candado): void {
            $candado->rechazaUnDsnAjeno($dsn);
        })->with($this->dsnAjenos ?? self::DSN_AJENOS);

        it('acepta el GlitchTip municipal', function (string $dsn) use ($candado): void {
            $candado->aceptaUnDsnPropio($dsn);
        })->with($this->dsnPropios ?? self::DSN_PROPIOS);

        it('un DSN ilegible no se da por bueno', function (string $dsn) use ($candado): void {
            $candado->noDaPorBuenoUnDsnIlegible($dsn);
        })->with($this->dsnIlegibles ?? self::DSN_ILEGIBLES);

        it('bootstrap/app.php usa la comprobación y no class_exists a secas', function () use ($candado): void {
            $candado->elBootstrapUsaLaComprobacion();
        });
    }

    public function sinDsnNoSeEnganchaNada(): void
    {
        config()->set('sentry.dsn', null);

        Assert::assertFalse($this->vaADestinoPropio(), 'sin DSN se enganchó el reporte de errores igual');
    }

    public function rechazaUnDsnAjeno(string $dsn): void
    {
        config()->set('sentry.dsn', $dsn);

        Assert::assertFalse(
            $this->vaADestinoPropio(),
            "«{$dsn}» se aceptó: las trazas del sistema se irían a un servicio extranjero"
        );
    }

    public function aceptaUnDsnPropio(string $dsn): void
    {
        config()->set('sentry.dsn', $dsn);

        Assert::assertTrue(
            $this->vaADestinoPropio(),
            "«{$dsn}» se rechazó: los errores del sistema quedarían sin reportar"
        );
    }

    public function noDaPorBuenoUnDsnIlegible(string $dsn): void
    {
        // Ante la duda, no se manda nada. Es preferible perder el reporte de un
        // error a mandarlo a donde no corresponde.
        config()->set('sentry.dsn', $dsn);

        Assert::assertFalse(
            $this->vaADestinoPropio(),
            "«{$dsn}» no se puede interpretar y aun así se dio por bueno"
        );
    }

    public function elBootstrapUsaLaComprobacion(): void
    {
        // El candado de verdad: si alguien vuelve al `class_exists` de antes, las
        // pruebas de arriba siguen verdes —la clase se comporta bien— y el sistema
        // vuelve a mandar errores a donde sea. Lo que hay que vigilar es el enganche.
        $bootstrap = $this->contenidoDe($this->bootstrap ?? $this->ruta('bootstrap/app.php'), 'bootstrap/app.php');

        Assert::assertTrue(
            str_contains($bootstrap, $this->llamada()),
            'bootstrap/app.php engancha Sentry sin comprobar a dónde van las trazas: falta '.$this->llamada()
        );
    }

    /**
     * Cómo se ve la comprobación en el bootstrap: `ReporteDeErrores::vaADestinoPropio()`.
     */
    private function llamada(): string
    {
        $partes = explode('\\', $this->clase);

        return (string) end($partes).'::'.$this->metodo.'()';
    }

    private function vaADestinoPropio(): bool
    {
        $evaluador = [$this->clase, $this->metodo];

        if (! is_callable($evaluador)) {
            Assert::fail("no existe {$this->clase}::{$this->metodo}(): nadie decide a dónde van las trazas de este sistema");
        }

        $resultado = $evaluador();

        Assert::assertIsBool($resultado, "{$this->clase}::{$this->metodo}() tiene que devolver un bool");

        return $resultado;
    }
}
