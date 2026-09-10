<?php

declare(strict_types=1);

namespace Muni\Candados\Candados;

use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Gate;
use Muni\Candados\Candado;
use PHPUnit\Framework\Assert;
use ReflectionMethod;

/**
 * Candado: ningún Resource de Filament queda sin autorización real.
 *
 * El autodescubrimiento de Laravel (`Gate::guessPolicyName()`) solo construye
 * candidatos `App\Policies\*` a partir de modelos que viven en `App\Models`.
 * Si el modelo del Resource viene de un paquete —`Spatie\Activitylog\Models\Activity`,
 * o `Muni\Shared\Onboarding\OnboardingTour` tras adoptar el paquete
 * compartido— nunca la encuentra, y hace falta un `Gate::policy()` explícito.
 *
 * Y lo contraintuitivo: cuando NO hay policy para el modelo y el modo
 * estricto está apagado (ninguno de los nueve sistemas lo configura),
 * Filament devuelve `Response::allow()` (ver `vendor/filament/filament/src/helpers.php`,
 * función `get_authorization_response()`). La ausencia de policy CONCEDE, no
 * deniega. El archivo `XPolicy.php` puede existir, su test unitario puede
 * pasar, y la autorización real nunca lo consulta.
 *
 * Por eso el candado no confía en que exista un archivo con el nombre
 * correcto: barre `Filament::getPanels()` y, para cada Resource, le pregunta
 * a `Gate::getPolicyFor()` —lo mismo que Filament consulta en el request
 * real— si hay una policy resuelta. Si no hay, la única salida legítima es
 * que el propio Resource sobreescriba `canViewAny()`: eso se comprueba con
 * `ReflectionMethod::getDeclaringClass()`, para no marcar en rojo a un
 * sistema que autoriza a mano y a propósito.
 *
 * No está en `Candados::todos()` (ver el docblock del método en Candados.php):
 * no hay medición de cuántos de los nueve sistemas lo cumplen hoy, y meterlo
 * a ciegas podría poner varias suites en rojo de golpe.
 */
final class NingunResourceSinAutorizacion extends Candado
{
    /**
     * @param  array<class-string, class-string>  $politicasExactas  modelo => policy que TIENE que resolver, además del barrido general
     */
    public function __construct(
        private readonly array $politicasExactas = [],
    ) {}

    public function registrar(): void
    {
        $candado = $this;

        it('todo Resource de Filament tiene una policy resuelta para su modelo, o autoriza con su propio canViewAny()', function () use ($candado): void {
            $candado->todoResourceAutorizaDeVerdad();
        });

        if ($this->politicasExactas !== []) {
            it('las policies exactas declaradas quedan registradas para su modelo', function () use ($candado): void {
                $candado->lasPoliticasExactasEstanRegistradas();
            });
        }
    }

    /**
     * El barrido: recorre todos los paneles y todos sus Resources, sin lista
     * que nadie tenga que mantener.
     */
    public function todoResourceAutorizaDeVerdad(): void
    {
        $fallos = [];

        foreach (Filament::getPanels() as $panel) {
            foreach ($panel->getResources() as $resourceClase) {
                if (! class_exists($resourceClase)) {
                    continue;
                }

                $modelo = $resourceClase::getModel();

                if (Gate::getPolicyFor($modelo) !== null) {
                    continue;
                }

                if ($this->declaraSuPropioCanViewAny($resourceClase)) {
                    continue;
                }

                $fallos[] = "{$resourceClase} (modelo {$modelo}) no tiene ninguna policy resuelta para «{$modelo}» "
                    .'ni sobreescribe canViewAny(): sin autorización explícita, Filament concede el acceso '
                    ."a cualquiera (Response::allow() por omisión). Registrá Gate::policy({$modelo}::class, "
                    .'TuPolicy::class) —en un AuthServiceProvider, si el modelo no vive en App\\Models— o '
                    ."implementá canViewAny() en {$resourceClase}.";
            }
        }

        Assert::assertSame(
            [],
            $fallos,
            "hay Resources de Filament sin autorización real:\n".implode("\n", $fallos)
        );
    }

    /**
     * La lista explícita opcional: afirma la clase EXACTA, no solo que exista
     * alguna. Sirve para fijar el arreglo de un modelo puntual (el de
     * `OnboardingTourPolicy`, por ejemplo) más allá del barrido general.
     */
    public function lasPoliticasExactasEstanRegistradas(): void
    {
        foreach ($this->politicasExactas as $modelo => $policyEsperada) {
            $policyReal = Gate::getPolicyFor($modelo);
            $policyReal = is_object($policyReal) ? $policyReal::class : $policyReal;

            Assert::assertSame(
                $policyEsperada,
                $policyReal,
                "«{$modelo}» tiene que resolver a {$policyEsperada} y resolvió a "
                .($policyReal ?? 'ninguna policy').': revisá el Gate::policy() que lo registra'
            );
        }
    }

    /**
     * Comprobable con la clase que declara el método, no con su resultado: un
     * Resource que sobreescribe `canViewAny()` para devolver `false` a
     * propósito sigue autorizando a mano, y no tiene por qué tener policy.
     */
    private function declaraSuPropioCanViewAny(string $resourceClase): bool
    {
        if (! method_exists($resourceClase, 'canViewAny')) {
            return false;
        }

        $metodo = new ReflectionMethod($resourceClase, 'canViewAny');

        return $metodo->getDeclaringClass()->getName() !== Resource::class;
    }
}
