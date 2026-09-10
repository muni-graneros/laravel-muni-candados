<?php

declare(strict_types=1);

namespace Muni\Candados\Candados;

use Muni\Candados\Candado;
use PHPUnit\Framework\Assert;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Candado de los seeders. Los usuarios de demostración tienen contraseña conocida
 * y uno de ellos es super administrador: en un servidor de producción son una
 * puerta abierta. Y como se siembran con `updateOrCreate`, borrarlos a mano no
 * alcanza — vuelven en la corrida siguiente del seeder.
 *
 * Este candado recorre TODOS los seeders del proyecto, así que sigue protegiendo
 * a los sistemas generados a partir del scaffold cuando agreguen los suyos.
 */
final class SeedersSinCredencialesEnProduccion extends Candado
{
    /**
     * Lo que delata a un seeder que crea credenciales.
     */
    private const string SIEMBRA = 'Hash::make(';

    /**
     * Vale cualquiera de las dos formas de preguntar por el entorno.
     */
    private const array GUARDIAS = ["environment('production')", 'isProduction()'];

    public function __construct(private readonly ?string $rutaDeSeeders = null) {}

    public function registrar(): void
    {
        $candado = $this;

        it('ningún seeder siembra credenciales sin excluir producción', function () use ($candado): void {
            $candado->ningunSeederSiembraCredencialesSinExcluirProduccion();
        });

        it('la guardia de entorno va antes de la primera contraseña', function () use ($candado): void {
            $candado->laGuardiaDeEntornoVaAntesDeLaPrimeraContrasena();
        });
    }

    public function ningunSeederSiembraCredencialesSinExcluirProduccion(): void
    {
        $sinGuardia = [];

        foreach ($this->seeders() as $archivo) {
            $codigo = $archivo->getContents();

            // Solo interesan los que crean credenciales.
            if (! str_contains($codigo, self::SIEMBRA)) {
                continue;
            }

            if ($this->posicionDeLaGuardia($codigo) !== null) {
                continue;
            }

            $sinGuardia[] = $archivo->getFilename();
        }

        Assert::assertSame(
            [],
            $sinGuardia,
            'siembran credenciales también en producción: '.implode(', ', $sinGuardia)
        );
    }

    public function laGuardiaDeEntornoVaAntesDeLaPrimeraContrasena(): void
    {
        // Candado de forma, complementario al de comportamiento: la comprobación
        // del entorno tiene que estar ANTES de crear la primera cuenta. Da igual
        // cómo se escriba —un `return` temprano o un `if` que llama a un método
        // privado—, lo que no vale es preguntar por el entorno después de haber
        // sembrado.
        $tardias = [];

        foreach ($this->seeders() as $archivo) {
            $codigo = $archivo->getContents();

            $primeraClave = strpos($codigo, self::SIEMBRA);

            if ($primeraClave === false) {
                continue;
            }

            $guardia = $this->posicionDeLaGuardia($codigo) ?? PHP_INT_MAX;

            if ($guardia < $primeraClave) {
                continue;
            }

            $tardias[] = $archivo->getFilename().': siembra una contraseña antes de mirar el entorno';
        }

        // Que ningún seeder siembre credenciales (self::SIEMBRA) es un estado
        // legítimo —no todo sistema tiene un seeder de demo—, pero antes esta
        // aserción vivía DENTRO del bucle, gateada por archivo: sin ningún
        // seeder que use Hash::make, el bucle corría cero veces y el test
        // quedaba sin ninguna comprobación real. PHPUnit lo marca «risky», que
        // es indistinguible de un candado que pasa por buenas razones —el
        // mismo hueco que dejó pasar una vez el incidente de `public/sw.js` en
        // seguridad-graneros (ver PwaSinRestosDelScaffold)—. Por eso la
        // aserción corre siempre, incondicional después del bucle.
        Assert::assertSame([], $tardias, implode("\n", $tardias));
    }

    /**
     * Dónde aparece por primera vez una pregunta por el entorno, si aparece.
     */
    private function posicionDeLaGuardia(string $codigo): ?int
    {
        $posiciones = [];

        foreach (self::GUARDIAS as $guardia) {
            $posicion = strpos($codigo, $guardia);

            if ($posicion !== false) {
                $posiciones[] = $posicion;
            }
        }

        return $posiciones === [] ? null : min($posiciones);
    }

    /**
     * @return list<SplFileInfo>
     */
    private function seeders(): array
    {
        $ruta = $this->rutaDeSeeders ?? $this->ruta('database/seeders');

        if (! is_dir($ruta)) {
            Assert::fail("no existe el directorio de seeders «{$ruta}»");
        }

        $archivos = [];

        foreach (Finder::create()->files()->in($ruta)->name('*.php')->sortByName() as $archivo) {
            $archivos[] = $archivo;
        }

        return $archivos;
    }
}
