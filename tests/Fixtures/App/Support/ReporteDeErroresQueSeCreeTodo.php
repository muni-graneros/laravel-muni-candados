<?php

declare(strict_types=1);

namespace Muni\Candados\Tests\Fixtures\App\Support;

/**
 * La clase que NO cumple: le basta con que haya algo escrito en el DSN. Es el
 * `class_exists(Integration::class)` de antes, con otro nombre: sentry.io
 * pasa, y una URL ilegible también.
 */
final class ReporteDeErroresQueSeCreeTodo
{
    public static function vaADestinoPropio(): bool
    {
        return trim((string) config('sentry.dsn')) !== '';
    }
}
