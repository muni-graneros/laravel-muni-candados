<?php

declare(strict_types=1);

namespace Muni\Candados\Tests\Fixtures\App\Filament\Resources;

use Filament\Resources\Resource;
use Muni\Candados\Tests\Fixtures\App\Models\OtroModeloDePaquete;

/**
 * Cumple: su modelo tiene una policy registrada explícitamente con
 * `Gate::policy()` (ver el test), aunque el modelo no viva en `App\Models`.
 */
class ResourceConPolicyRegistrada extends Resource
{
    protected static ?string $model = OtroModeloDePaquete::class;
}
