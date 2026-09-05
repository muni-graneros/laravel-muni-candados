<?php

declare(strict_types=1);

namespace Muni\Candados\Candados;

use Muni\Candados\Candado;
use PHPUnit\Framework\Assert;

/**
 * La guarda de credenciales de plantilla (`Muni\Shared\Seguridad\CredencialesDePlantilla`,
 * en `laravel-muni-shared`) solo protege si el sistema la tiene de verdad enganchada al
 * arranque. La clase existiendo en el vendor no alcanza: el scaffold ya aprendió esa
 * lección con su propia copia («una salvaguarda que nadie llama no protege de nada»), y
 * la auditoría que originó esta guarda encontró justamente eso —el paquete compartido
 * instalado en cero de los ocho sistemas—.
 *
 * `laravel-muni-shared` la engancha SOLA, desde `MuniSharedServiceProvider::boot()`, así
 * que un sistema no escribe ninguna línea propia para activarla: instalar el paquete
 * alcanza. Eso significa que lo único que puede dejarla sin enganchar es, o bien que el
 * sistema nunca requirió el paquete, o bien que le apagó a mano el auto-descubrimiento de
 * su proveedor —la única forma en Laravel de tener la clase en el vendor y arrancar sin
 * que su `boot()` corra—. Este candado vigila esas dos cosas, mirando el `composer.json`
 * del sistema; no necesita levantar Laravel para hacerlo.
 */
final class GuardaDeCredencialesDePlantilla extends Candado
{
    private const string PAQUETE = 'muni-graneros/laravel-muni-shared';

    private const string PROVEEDOR = 'Muni\\Shared\\MuniSharedServiceProvider';

    public function __construct(private readonly ?string $composerJson = null) {}

    public function registrar(): void
    {
        $candado = $this;

        it('el paquete que trae la guarda de credenciales de plantilla está requerido', function () use ($candado): void {
            $candado->elPaqueteEstaRequerido();
        });

        it('el sistema no le apaga el auto-descubrimiento al proveedor que engancha la guarda', function () use ($candado): void {
            $candado->elProveedorNoEstaExcluidoDelDescubrimiento();
        });
    }

    public function elPaqueteEstaRequerido(): void
    {
        $require = $this->composer()['require'] ?? [];

        Assert::assertIsArray($require);

        Assert::assertArrayHasKey(
            self::PAQUETE,
            $require,
            'el composer.json no requiere '.self::PAQUETE.': sin instalarlo, la guarda de '
            .'credenciales de plantilla no existe en este sistema, y arranca en producción '
            .'con la contraseña del .env.example del scaffold sin que nada avise'
        );
    }

    public function elProveedorNoEstaExcluidoDelDescubrimiento(): void
    {
        $excluidos = $this->composer()['extra']['laravel']['dont-discover'] ?? [];

        Assert::assertIsArray($excluidos);

        // «*» apaga TODO el auto-descubrimiento, paquete por paquete: ningún proveedor de
        // ningún paquete se registra solo, el de la guarda incluido.
        Assert::assertNotContains(
            '*',
            $excluidos,
            'el composer.json apaga TODO el auto-descubrimiento de paquetes ("dont-discover": ["*"]): '
            .self::PROVEEDOR.' no se registra solo, y con él tampoco la guarda de credenciales de plantilla'
        );

        Assert::assertNotContains(
            self::PAQUETE,
            $excluidos,
            'el composer.json excluye '.self::PAQUETE.' del auto-descubrimiento: '.self::PROVEEDOR
            .' nunca se registra, y la guarda de credenciales de plantilla queda en el vendor sin arrancar nunca'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function composer(): array
    {
        $contenido = $this->contenidoDe($this->composerJson ?? $this->ruta('composer.json'), 'el composer.json');

        $decodificado = json_decode($contenido, true);

        if (! is_array($decodificado)) {
            Assert::fail('el composer.json no es JSON válido');
        }

        /** @var array<string, mixed> $decodificado */
        return $decodificado;
    }
}
