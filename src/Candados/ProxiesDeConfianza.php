<?php

declare(strict_types=1);

namespace Muni\Candados\Candados;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;
use Muni\Candados\Candado;
use PHPUnit\Framework\Assert;
use ReflectionProperty;

/**
 * En qué proxies se confía para saber de dónde viene una petición.
 *
 * `X-Forwarded-For` la escribe quien quiera: es una cabecera más. Solo tiene valor si
 * el único que puede escribirla es un intermediario propio, y para eso hay que decir
 * cuál. Confiando en todos («*»), cualquiera declara la dirección que se le antoje y la
 * aplicación se la cree.
 *
 * No es un detalle de registro: de la dirección dependen el conteo de intentos de
 * acceso —que se cuenta por origen— y cualquier límite por IP. Con «*» esos controles
 * se evaden cambiando una cabecera, y encima los registros pasan a decir lo que el
 * atacante quiera.
 *
 * Se configura por entorno y no fijo en el código, porque el rango del intermediario lo
 * decide cada instalación. Y se declara UNA vez, en el AppServiceProvider: el bootstrap
 * corre antes de que la configuración esté cargada, así que una lista ahí es código
 * muerto que dice otra cosa.
 */
final class ProxiesDeConfianza extends Candado
{
    /**
     * Los comodines que hacen confiar en todo el mundo.
     */
    private const array COMODINES = ['*', '**'];

    public function __construct(
        private readonly string $claveDeConfiguracion = 'proxies.confiables',
        private readonly ?string $bootstrap = null,
        private readonly ?string $proveedor = null,
    ) {}

    public function registrar(): void
    {
        $candado = $this;

        it('no se confía en cualquier proxy', function () use ($candado): void {
            $candado->noSeConfiaEnCualquierProxy();
        });

        it('la lista de proxies sale de la configuración, no del código', function () use ($candado): void {
            $candado->laListaDeProxiesSaleDeLaConfiguracion();
        });

        it('la lista se declara UNA vez, y desde la configuración', function () use ($candado): void {
            $candado->laListaSeDeclaraUnaVezYDesdeLaConfiguracion();
        });

        it('una cabecera de origen falsificada no cambia la dirección que ve la aplicación', function () use ($candado): void {
            $candado->unaCabeceraDeOrigenFalsificadaNoCambiaLaDireccionQueVeLaAplicacion();
        });
    }

    public function noSeConfiaEnCualquierProxy(): void
    {
        // Lo que fija `TrustProxies::at()` queda en esta propiedad estática. Se lee
        // por reflexión porque es protegida: la alternativa sería duplicar acá el
        // valor esperado, y entonces el test comprobaría su propia copia y no la real.
        $propiedad = new ReflectionProperty(TrustProxies::class, 'alwaysTrustProxies');
        $confiables = $propiedad->getValue();

        $esComodin = is_string($confiables)
            ? in_array($confiables, self::COMODINES, true)
            : (is_array($confiables) && array_intersect($confiables, self::COMODINES) !== []);

        Assert::assertFalse(
            $esComodin,
            'se confía en cualquier proxy: cualquiera puede declarar su dirección de '
            .'origen y evadir lo que se cuente por IP'
        );
    }

    public function laListaDeProxiesSaleDeLaConfiguracion(): void
    {
        // Cada instalación tiene su intermediario y su rango. Fijarlo en el código
        // obliga a tocar el repositorio para desplegar, que es como se termina
        // volviendo a «*».
        Assert::assertNotNull(
            config($this->claveDeConfiguracion),
            "no hay una opción de configuración «{$this->claveDeConfiguracion}» para los proxies de confianza"
        );
    }

    public function laListaSeDeclaraUnaVezYDesdeLaConfiguracion(): void
    {
        // Estuvo declarada dos veces con listas distintas: una fija en
        // `bootstrap/app.php` y otra desde la configuración en el AppServiceProvider.
        // Ganaba la segunda —corre en el boot, después—, así que la primera era
        // código muerto que decía otra cosa: quien leyera el bootstrap creería que
        // esa es la lista y cambiaría ahí, sin efecto alguno.
        //
        // El bootstrap además NO puede leer configuración: se ejecuta antes de que
        // esté cargada, y por eso la declaración vive en el proveedor.
        $bootstrap = $this->contenidoDe($this->bootstrap ?? $this->ruta('bootstrap/app.php'), 'bootstrap/app.php');

        Assert::assertFalse(
            str_contains($bootstrap, 'trustProxies'),
            'bootstrap/app.php vuelve a declarar los proxies: son dos listas que no coinciden, '
            .'y la de acá no tiene efecto porque gana la del AppServiceProvider'
        );

        $proveedor = $this->contenidoDe(
            $this->proveedor ?? $this->ruta('app/Providers/AppServiceProvider.php'),
            'el AppServiceProvider'
        );

        $esperado = "TrustProxies::at(config('{$this->claveDeConfiguracion}'))";

        Assert::assertTrue(
            str_contains($proveedor, $esperado),
            "el AppServiceProvider no declara los proxies desde la configuración: falta {$esperado}"
        );
    }

    public function unaCabeceraDeOrigenFalsificadaNoCambiaLaDireccionQueVeLaAplicacion(): void
    {
        // La comprobación que de verdad importa: el comportamiento. Se pide desde una
        // dirección que no es la del intermediario y se declara otra en la cabecera; la
        // aplicación tiene que quedarse con la real.
        //
        // La ruta se define acá y no se reutiliza `/up`: el healthcheck de varios
        // sistemas del ecosistema abre la base de datos y hace ping a Redis, así que
        // responde 500 en un entorno de pruebas sin esos servicios y el test se caía
        // por algo que no tiene nada que ver con los proxies.
        Route::get('/__prueba-de-proxies', fn (): string => (string) request()->ip());

        $respuesta = $this->caso()
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.7'])
            ->get('/__prueba-de-proxies');

        $respuesta->assertOk();

        $direccion = (string) $respuesta->getContent();

        Assert::assertNotSame(
            '198.51.100.7',
            $direccion,
            'la aplicación se creyó la dirección declarada en la cabecera'
        );
        Assert::assertSame(
            '203.0.113.9',
            $direccion,
            'la aplicación no ve la dirección real desde la que se pidió'
        );
    }
}
