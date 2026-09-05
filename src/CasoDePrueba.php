<?php

declare(strict_types=1);

namespace Muni\Candados;

use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Testing\TestResponse;
use LogicException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * El caso de prueba en curso, visto solo por lo que un candado necesita de él:
 * hacer peticiones como lo haría `$this` en un test escrito a mano.
 *
 * No se exige una clase concreta a propósito. En un sistema, `Tests\TestCase`
 * extiende `Illuminate\Foundation\Testing\TestCase`; en un paquete probado con
 * Testbench, la base es la de PHPUnit con los traits de Laravel encima. Las dos
 * sirven: lo que importa es que el caso sepa hacer peticiones
 * (`MakesHttpRequests`), y eso es lo único que se comprueba.
 *
 * Igual que en Laravel, `withCookie()`, `withHeaders()` y `withServerVariables()`
 * quedan puestos para TODAS las peticiones que siguen dentro del mismo test.
 */
final class CasoDePrueba
{
    private function __construct(private readonly TestCase $caso) {}

    public static function desde(TestCase $caso): self
    {
        if (! in_array(MakesHttpRequests::class, class_uses_recursive($caso), true)) {
            throw new LogicException(sprintf(
                'Este candado necesita correr dentro de un test que sepa hacer peticiones '
                .'(el TestCase de Laravel, en tests/Feature); %s no usa %s.',
                $caso::class,
                MakesHttpRequests::class,
            ));
        }

        return new self($caso);
    }

    public function withCookie(string $nombre, string $valor): self
    {
        $this->llamar('withCookie', [$nombre, $valor]);

        return $this;
    }

    /**
     * @param  array<string, string>  $cabeceras
     */
    public function withHeaders(array $cabeceras): self
    {
        $this->llamar('withHeaders', [$cabeceras]);

        return $this;
    }

    /**
     * @param  array<string, string>  $variables
     */
    public function withServerVariables(array $variables): self
    {
        $this->llamar('withServerVariables', [$variables]);

        return $this;
    }

    /**
     * @return TestResponse<Response>
     */
    public function get(string $uri): TestResponse
    {
        return $this->respuesta($this->llamar('get', [$uri]));
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return TestResponse<Response>
     */
    public function post(string $uri, array $datos = []): TestResponse
    {
        return $this->respuesta($this->llamar('post', [$uri, $datos]));
    }

    /**
     * Marca la prueba como omitida, con el motivo a la vista.
     */
    public function omitir(string $motivo): never
    {
        $this->caso->markTestSkipped($motivo);
    }

    /**
     * @param  list<mixed>  $argumentos
     */
    private function llamar(string $metodo, array $argumentos): mixed
    {
        $invocable = [$this->caso, $metodo];

        if (! is_callable($invocable)) {
            Assert::fail(sprintf('el caso de prueba %s no sabe hacer %s()', $this->caso::class, $metodo));
        }

        return $invocable(...$argumentos);
    }

    /**
     * @return TestResponse<Response>
     */
    private function respuesta(mixed $respuesta): TestResponse
    {
        Assert::assertInstanceOf(TestResponse::class, $respuesta, 'la petición no devolvió una respuesta de prueba');

        return $respuesta;
    }
}
