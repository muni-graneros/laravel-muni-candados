<?php

declare(strict_types=1);

namespace Muni\Candados\Tests\Fixtures\App\Support;

/**
 * Copia de `App\Support\ReporteDeErrores` de los sistemas del ecosistema: la
 * clase que CUMPLE. Mira el HOST del DSN, por sufijo, y ante la duda no manda
 * nada.
 */
final class ReporteDeErrores
{
    /**
     * Se compara por SUFIJO DE HOST, no con `str_contains`: `errores.sentry.io`
     * tiene que caer, y un hipotético `sentry.io.graneros.cl` —que contiene la
     * cadena pero es nuestro— no.
     */
    private const array AJENOS = ['sentry.io'];

    public static function vaADestinoPropio(): bool
    {
        $dsn = trim((string) config('sentry.dsn'));

        if ($dsn === '') {
            return false;
        }

        $host = parse_url($dsn, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        foreach (self::AJENOS as $ajeno) {
            if ($host === $ajeno || str_ends_with($host, '.'.$ajeno)) {
                return false;
            }
        }

        return true;
    }
}
