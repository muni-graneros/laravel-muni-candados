<?php

declare(strict_types=1);

namespace Muni\Candados\Tests\Fixtures\App\Providers;

/**
 * El mismo panel, tal como lo deja `filament:install`: sin el middleware que
 * ignora la cookie de recordar. Con esto el guard vuelve a autenticar en
 * cuanto encuentra la cookie, que es lo que el candado tiene que detectar.
 */
class PanelSinCandadoProvider extends PanelDePruebaProvider
{
    /**
     * @return list<class-string>
     */
    protected function middlewareDelPanel(): array
    {
        return $this->middlewareHeredadoDeFilament();
    }
}
