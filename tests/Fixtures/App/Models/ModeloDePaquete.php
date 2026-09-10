<?php

declare(strict_types=1);

namespace Muni\Candados\Tests\Fixtures\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un modelo que vive FUERA de `App\Models`, como quedan
 * `Spatie\Activitylog\Models\Activity` o `Muni\Shared\Onboarding\OnboardingTour`
 * al adoptar un paquete compartido.
 *
 * `Gate::guessPolicyName()` solo construye candidatos `App\Policies\*` a
 * partir de modelos que viven en `App\Models`: para un modelo como este, el
 * autodescubrimiento nunca encuentra nada, y hace falta un `Gate::policy()`
 * explícito. Sirve para las dos fixtures «sin policy» del candado: ninguna de
 * las dos puede depender de la convención de nombres, porque el modelo real
 * que dispara el agujero tampoco puede.
 */
class ModeloDePaquete extends Model
{
    protected $table = 'modelos_de_paquete';

    protected $guarded = [];
}
