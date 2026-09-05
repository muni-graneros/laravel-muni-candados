<?php

declare(strict_types=1);

namespace Muni\Candados;

use Illuminate\Container\Container;
use LogicException;
use Pest\Support\HigherOrderTapProxy;
use PHPUnit\Framework\Assert;

/**
 * Lo que comparten todos los candados.
 *
 * Un candado no prueba una funcionalidad: prueba una REGLA del ecosistema
 * («el seeder de demo no corre en producción», «los errores no salen del
 * país») escrita como test, para que ningún sistema pueda desobedecerla en
 * silencio. Cada candado sabe registrarse en el archivo de test que lo llama
 * (`registrar()`) y expone sus comprobaciones como métodos públicos, para que
 * la suite de este paquete pueda ejercitarlas contra una fixture que cumple y
 * contra otra que no.
 *
 * Los closures que se le entregan a `it()` se crean SIEMPRE dentro de métodos
 * de instancia: un closure creado en contexto estático es estático, y Pest no
 * puede hacerle `bind` al caso de prueba. Y dentro de esos closures no se usa
 * `$this` —Pest lo reemplaza por el TestCase—: se captura `$candado = $this`,
 * y el caso de prueba en curso se pide con `caso()`.
 */
abstract class Candado
{
    /**
     * Registra las pruebas del candado en el archivo de test que lo llama.
     *
     * Pest atribuye cada `it()` al archivo más externo de la pila —el del
     * consumidor—, así que el candado aparece en la suite del sistema como si
     * estuviera escrito ahí.
     */
    abstract public function registrar(): void;

    /**
     * Raíz del proyecto que corre los tests.
     *
     * Con la aplicación arrancada es `base_path()`; sin ella —un test de
     * `tests/Unit` que no levanta Laravel— es el directorio desde el que se
     * corre Pest, que en todos los sistemas del ecosistema es la raíz del repo.
     */
    protected function raiz(): string
    {
        $contenedor = Container::getInstance();

        if ($contenedor->bound('path.base')) {
            $base = $contenedor->make('path.base');

            if (is_string($base) && $base !== '') {
                return $base;
            }
        }

        return (string) getcwd();
    }

    /**
     * Una ruta relativa a la raíz del proyecto.
     */
    protected function ruta(string $relativa): string
    {
        return rtrim($this->raiz(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($relativa, DIRECTORY_SEPARATOR);
    }

    /**
     * El caso de prueba que está corriendo ahora mismo.
     *
     * Es lo que en un test escrito a mano sería `$this`: `withCookie()`,
     * `get()`, `markTestSkipped()`. Solo existe cuando el archivo del
     * consumidor extiende un TestCase que sepa hacer peticiones (el de
     * Laravel, en `tests/Feature`).
     */
    protected function caso(): CasoDePrueba
    {
        $actual = test();

        if (! $actual instanceof HigherOrderTapProxy) {
            throw new LogicException('Este candado solo se puede ejercitar desde dentro de una prueba en curso.');
        }

        return CasoDePrueba::desde($actual->target);
    }

    /**
     * El contenido de un archivo del proyecto, o una falla clara si no existe.
     *
     * Un archivo que falta no es «cumple»: es o un sistema que perdió algo que
     * tenía o un candado mal apuntado, y las dos cosas hay que verlas.
     */
    protected function contenidoDe(string $ruta, string $queEs): string
    {
        if (! is_file($ruta)) {
            Assert::fail("no existe {$queEs} en «{$ruta}»");
        }

        return (string) file_get_contents($ruta);
    }
}
