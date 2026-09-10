<?php

declare(strict_types=1);

namespace Muni\Candados\Tests\Fixtures\App\Filament\Resources;

use Filament\Resources\Resource;
use Muni\Candados\Tests\Fixtures\App\Models\ModeloDePaquete;

/**
 * No cumple: sin policy registrada para su modelo y sin sobreescribir
 * `canViewAny()`. Es el agujero real —Filament devuelve `Response::allow()`
 * por omisión— y el candado tiene que marcarlo en rojo.
 */
class ResourceSinPolicyNiCanViewAny extends Resource
{
    protected static ?string $model = ModeloDePaquete::class;
}
