<?php

declare(strict_types=1);

namespace Muni\Candados\Candados;

use Closure;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Muni\Candados\Candado;
use PHPUnit\Framework\Assert;

/**
 * La cookie de «Recordarme» no autentica en NINGUNA ruta.
 *
 * El rechazo de «viaRemember()» que corre en el panel llega tarde para las
 * rutas de fuera: el guard vuelve a autenticar en cuanto encuentra la cookie
 * —la portada, el «guest» del login, una API con grupo web— y en esa misma
 * petición escribe el id del usuario en la sesión. En la siguiente,
 * «viaRemember()» ya es falso y el panel no distingue esa sesión de una
 * abierta con contraseña: se entra sin escribirla nunca y el segundo factor
 * queda de único factor.
 *
 * Estas pruebas cubren justo esa PRIMERA petición, la que abría la puerta, y
 * que el navegador se quede sin la cookie en vez de traerla catorce meses. Y
 * que la migración que olvida los testigos repartidos antes del arreglo siga
 * haciendo su trabajo: mientras un testigo valga en la tabla, la cookie sirve
 * por cualquier camino que se le escape al middleware.
 *
 * Necesita base de datos: el candado aplica `RefreshDatabase` al archivo que
 * lo hereda.
 */
final class CookieDeRecordarInerte extends Candado
{
    /**
     * La migración que vacía `remember_token`, tal como se llama en los
     * sistemas generados desde el scaffold.
     */
    private const string MIGRACION = 'database/migrations/2026_09_02_120000_olvidar_los_testigos_de_recordarme.php';

    /**
     * @param  (Closure(): Model)|null  $crearUsuario  la persona tiene que poder entrar con la contraseña «password»
     * @param  array<string, mixed>  $configuracion
     */
    public function __construct(
        private readonly ?string $migracion = null,
        private readonly ?string $urlDelPanel = null,
        private readonly ?string $urlDeAcceso = null,
        private readonly string $rutaDeAccesoFuera = 'ingresar',
        private readonly string $rutaDeAccesoFueraPost = 'ingresar.post',
        private readonly string $guard = 'web',
        private readonly ?Closure $crearUsuario = null,
        private readonly array $configuracion = ['mfa.enabled' => true],
        private readonly string $portada = '/',
    ) {}

    public function registrar(): void
    {
        // Estas pruebas crean personas en la base: el archivo que las hereda
        // necesita la base limpia. `uses()` se atribuye al archivo del
        // consumidor, igual que los `it()`.
        uses(RefreshDatabase::class);

        $candado = $this;

        it('la portada no autentica desde la cookie y la vence en el navegador', function () use ($candado): void {
            $candado->laPortadaNoAutenticaDesdeLaCookieYLaVenceEnElNavegador();
        });

        it('la pantalla de acceso de fuera del panel tampoco autentica desde la cookie', function () use ($candado): void {
            $candado->laPantallaDeAccesoDeFueraDelPanelTampocoAutenticaDesdeLaCookie();
        });

        it('el panel vence la cookie además de rechazarla', function () use ($candado): void {
            $candado->elPanelVenceLaCookieAdemasDeRechazarla();
        });

        it('sin la cookie no toca nada', function () use ($candado): void {
            $candado->sinLaCookieNoTocaNada();
        });

        it('la migración olvida los testigos repartidos antes del arreglo', function () use ($candado): void {
            $candado->laMigracionOlvidaLosTestigosRepartidosAntesDelArreglo();
        });

        it('el formulario de acceso de fuera del panel no deja cookie de recordar aunque la pidan', function () use ($candado): void {
            $candado->elFormularioDeAccesoDeFueraDelPanelNoDejaCookieDeRecordarAunqueLaPidan();
        });
    }

    public function laPortadaNoAutenticaDesdeLaCookieYLaVenceEnElNavegador(): void
    {
        $caso = $this->caso();
        $persona = $this->personaQueVuelveConLaCookie();
        $nombre = $this->nombreDeLaCookie();

        $respuesta = $caso->withCookie($nombre, $this->valorDeLaCookie($persona))->get($this->portada);

        $respuesta->assertCookieExpired($nombre);
        Assert::assertFalse($this->guard()->check(), 'la portada dejó la sesión autenticada desde la cookie');
        Assert::assertNull(
            session()->get($this->guard()->getName()),
            'quedó el id del usuario escrito en la sesión'
        );

        // Y con esa sesión, el panel sigue pidiendo la contraseña.
        $caso->get($this->urlDelPanel())->assertRedirect($this->urlDeAcceso());
    }

    public function laPantallaDeAccesoDeFueraDelPanelTampocoAutenticaDesdeLaCookie(): void
    {
        $caso = $this->caso();

        if (! Route::has($this->rutaDeAccesoFuera)) {
            $caso->omitir('este sistema no tiene una pantalla de acceso fuera del panel');
        }

        $persona = $this->personaQueVuelveConLaCookie();
        $nombre = $this->nombreDeLaCookie();

        // El middleware «guest» resuelve al usuario para decidir si redirige: con
        // la cookie intacta, aquí es donde la sesión se abría sola.
        $respuesta = $caso->withCookie($nombre, $this->valorDeLaCookie($persona))
            ->get(route($this->rutaDeAccesoFuera));

        $respuesta->assertOk();
        $respuesta->assertCookieExpired($nombre);
        Assert::assertFalse(
            $this->guard()->check(),
            'el acceso de fuera del panel dejó la sesión autenticada desde la cookie'
        );
    }

    public function elPanelVenceLaCookieAdemasDeRechazarla(): void
    {
        $caso = $this->caso();
        $persona = $this->personaQueVuelveConLaCookie();
        $nombre = $this->nombreDeLaCookie();

        $caso->withCookie($nombre, $this->valorDeLaCookie($persona))
            ->get($this->urlDelPanel())
            ->assertRedirect($this->urlDeAcceso())
            ->assertCookieExpired($nombre);

        Assert::assertFalse($this->guard()->check(), 'el panel dejó la sesión autenticada desde la cookie');
    }

    public function sinLaCookieNoTocaNada(): void
    {
        $nombre = $this->nombreDeLaCookie();

        $nombresEnLaRespuesta = [];

        foreach ($this->caso()->get($this->portada)->baseResponse->headers->getCookies() as $cookie) {
            $nombresEnLaRespuesta[] = $cookie->getName();
        }

        Assert::assertNotContains($nombre, $nombresEnLaRespuesta, 'sin cookie de recordar en la petición, la respuesta trae una');
    }

    public function laMigracionOlvidaLosTestigosRepartidosAntesDelArreglo(): void
    {
        // Las cookies entregadas antes siguen guardadas en los navegadores y su
        // testigo sigue valiendo en la tabla. Mientras valga, sirven por cualquier
        // camino que se le escape al middleware —una ruta nueva fuera del grupo
        // «web», un panel que se agregue mañana—, y ya se escapó una vez.
        $persona = $this->personaQueVuelveConLaCookie();
        $testigoViejo = (string) $persona->getRememberToken();
        $identificador = $persona->getAuthIdentifier();
        $proveedor = $this->guard()->getProvider();

        // Antes de la migración, ese testigo todavía identifica a la persona: es
        // exactamente lo que la cookie le entrega al guard.
        Assert::assertNotNull(
            $proveedor->retrieveByToken($identificador, $testigoViejo),
            'el testigo recién creado tendría que identificar a la persona antes de la migración'
        );

        $ruta = $this->migracion ?? $this->ruta(self::MIGRACION);

        if (! is_file($ruta)) {
            Assert::fail("falta la migración que olvida los testigos de «Recordarme»: «{$ruta}»");
        }

        // `require` sin `_once` vuelve a evaluar el archivo y devuelve la clase.
        $migracion = require $ruta;

        Assert::assertInstanceOf(Migration::class, $migracion, 'la migración no devuelve una clase de migración');

        $subir = [$migracion, 'up'];

        if (! is_callable($subir)) {
            Assert::fail('la migración no tiene up()');
        }

        $subir();

        Assert::assertNull(
            $this->testigoGuardado($persona),
            'quedaron testigos de «Recordarme» válidos en la tabla'
        );
        Assert::assertNull(
            $proveedor->retrieveByToken($identificador, $testigoViejo),
            'el testigo viejo todavía identifica a la persona'
        );
    }

    public function elFormularioDeAccesoDeFueraDelPanelNoDejaCookieDeRecordarAunqueLaPidan(): void
    {
        $caso = $this->caso();

        if (! Route::has($this->rutaDeAccesoFueraPost)) {
            $caso->omitir('este sistema no tiene acceso fuera del panel');
        }

        $persona = $this->personaNueva();

        $respuesta = $caso->post(route($this->rutaDeAccesoFueraPost), [
            'email' => $persona->getAttribute('email'),
            'password' => 'password',
            'remember' => '1',
        ]);

        $respuesta->assertRedirect();
        $respuesta->assertCookieMissing($this->nombreDeLaCookie());
        Assert::assertNull(
            $this->testigoGuardado($persona),
            'el acceso de fuera del panel sigue emitiendo el testigo de recordar'
        );
    }

    /**
     * El testigo tal como está en la tabla ahora mismo, o null si no hay.
     *
     * Se lee la columna y no `getRememberToken()`: Laravel lo devuelve
     * convertido a cadena, así que un null en la base llega como «».
     */
    private function testigoGuardado(Model&Authenticatable $persona): ?string
    {
        $fresco = $persona->fresh();

        Assert::assertNotNull($fresco, 'la persona desapareció de la tabla en medio de la prueba');

        $testigo = $fresco->getAttribute($persona->getRememberTokenName());

        return is_string($testigo) && $testigo !== '' ? $testigo : null;
    }

    /**
     * El navegador que vuelve cuando la sesión ya venció: sin sesión, con la
     * cookie de recordar todavía guardada.
     */
    private function personaQueVuelveConLaCookie(): Model&Authenticatable
    {
        $persona = $this->personaNueva();
        $persona->setRememberToken(Str::random(60));
        $persona->save();

        return $persona;
    }

    /**
     * Una persona que puede entrar, sin testigo de recordar repartido.
     */
    private function personaNueva(): Model&Authenticatable
    {
        foreach ($this->configuracion as $clave => $valor) {
            config()->set($clave, $valor);
        }

        $persona = $this->crearUsuario !== null ? ($this->crearUsuario)() : $this->crearPersonaPorOmision();

        Assert::assertInstanceOf(Model::class, $persona, 'crearUsuario tiene que devolver un modelo Eloquent');
        Assert::assertInstanceOf(Authenticatable::class, $persona, 'crearUsuario tiene que devolver un usuario autenticable');

        // El contrato solo admite fijar un testigo, no borrarlo: se vacía la
        // columna por su nombre, que es lo que hace la migración del arreglo.
        $persona->setAttribute($persona->getRememberTokenName(), null);
        $persona->save();

        return $persona;
    }

    /**
     * Con la factory del modelo si la tiene —es lo que hacen los sistemas del
     * ecosistema— y, si no, con lo mínimo que pide la tabla `users` de Laravel.
     */
    private function crearPersonaPorOmision(): Model
    {
        $modelo = $this->modeloDeUsuario();
        $fabrica = [$modelo, 'factory'];

        if (is_callable($fabrica)) {
            $instancia = $fabrica();

            if ($instancia instanceof Factory) {
                $creado = $instancia->create();

                if ($creado instanceof Model) {
                    return $creado;
                }
            }
        }

        $persona = new $modelo;
        $persona->forceFill([
            'name' => 'Persona del candado',
            'email' => 'candado-'.Str::lower(Str::random(8)).'@example.test',
            'password' => Hash::make('password'),
        ])->save();

        return $persona;
    }

    /**
     * @return class-string<Model>
     */
    private function modeloDeUsuario(): string
    {
        $proveedor = config("auth.guards.{$this->guard}.provider");
        $modelo = is_string($proveedor) ? config("auth.providers.{$proveedor}.model") : null;

        if (! is_string($modelo) || ! is_a($modelo, Model::class, true)) {
            Assert::fail("no hay un modelo Eloquent de usuario para el guard «{$this->guard}»: pasale crearUsuario al candado");
        }

        return $modelo;
    }

    private function guard(): SessionGuard
    {
        $guard = Auth::guard($this->guard);

        Assert::assertInstanceOf(
            SessionGuard::class,
            $guard,
            "el guard «{$this->guard}» no es un guard de sesión: no reparte cookies de recordar"
        );

        return $guard;
    }

    /**
     * Calculado por el propio guard y no escrito a mano: el nombre lleva un hash
     * de la clase, así que copiarlo se rompe solo.
     */
    private function nombreDeLaCookie(): string
    {
        return $this->guard()->getRecallerName();
    }

    /**
     * Lo que Laravel guarda en la cookie: `id|testigo|hash de la contraseña`.
     */
    private function valorDeLaCookie(Model&Authenticatable $persona): string
    {
        $identificador = $persona->getAuthIdentifier();

        if (! is_int($identificador) && ! is_string($identificador)) {
            Assert::fail('el identificador de la persona no es un entero ni una cadena');
        }

        return $identificador.'|'.$persona->getRememberToken().'|'.$persona->getAuthPassword();
    }

    private function urlDelPanel(): string
    {
        return $this->urlDelPanel ?? (string) $this->panelDeFilament()->getUrl();
    }

    private function urlDeAcceso(): string
    {
        return $this->urlDeAcceso ?? (string) $this->panelDeFilament()->getLoginUrl();
    }

    /**
     * Se le pregunta a Filament en vez de escribir la dirección: no todos los
     * paneles del ecosistema se llaman «admin».
     */
    private function panelDeFilament(): Panel
    {
        if (! class_exists(Filament::class)) {
            Assert::fail('sin Filament instalado hay que pasarle al candado urlDelPanel y urlDeAcceso');
        }

        $panel = Filament::getCurrentOrDefaultPanel();

        if ($panel === null) {
            Assert::fail('no hay ningún panel de Filament registrado: pasale al candado urlDelPanel y urlDeAcceso');
        }

        return $panel;
    }
}
