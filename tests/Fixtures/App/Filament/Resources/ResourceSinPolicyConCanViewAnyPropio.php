<?php

declare(strict_types=1);

namespace Muni\Candados\Tests\Fixtures\App\Filament\Resources;

use Filament\Resources\Resource;
use Muni\Candados\Tests\Fixtures\App\Models\ModeloDePaquete;

/**
 * Cumple, aunque no tenga policy: autoriza a mano y a propósito
 * sobreescribiendo `canViewAny()`. El candado no puede marcarlo en rojo solo
 * porque no existe una policy — eso sería más estricto de lo que pide la
 * regla.
 */
class ResourceSinPolicyConCanViewAnyPropio extends Resource
{
    protected static ?string $model = ModeloDePaquete::class;

    public static function canViewAny(): bool
    {
        return true;
    }
}
