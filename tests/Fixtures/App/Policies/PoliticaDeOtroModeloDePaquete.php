<?php

declare(strict_types=1);

namespace Muni\Candados\Tests\Fixtures\App\Policies;

/**
 * El `Gate::policy()` explícito que un sistema tiene que escribir a mano
 * cuando el modelo del Resource viene de un paquete: el autodescubrimiento
 * de Laravel nunca la encuentra sola.
 */
class PoliticaDeOtroModeloDePaquete
{
    public function viewAny(): bool
    {
        return true;
    }
}
