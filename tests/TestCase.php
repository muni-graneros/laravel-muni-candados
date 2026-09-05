<?php

declare(strict_types=1);

namespace Muni\Candados\Tests;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as KernelDeLaravel;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Muni\Candados\Tests\Fixtures\App\Http\Middleware\IgnorarCookieDeRecordar;
use Muni\Candados\Tests\Fixtures\App\Models\Usuario;
use Muni\Candados\Tests\Fixtures\App\Providers\PanelDePruebaProvider;
use Muni\Candados\Tests\Fixtures\App\Support\ReporteDeErrores;
use Orchestra\Testbench\TestCase as Base;

use function Orchestra\Testbench\default_migration_path;

/**
 * La aplicación de mentira que CUMPLE todas las reglas del ecosistema.
 *
 * Es lo que un sistema generado desde el scaffold tiene: el middleware que
 * ignora la cookie de recordar primero en el grupo «web» y primero en el panel
 * de Filament, un acceso fuera del panel que no reparte cookies, los proxies
 * de confianza declarados desde la configuración, y —como archivos de texto en
 * `tests/Fixtures/Cumple`— el Dockerfile, los seeders, el bootstrap, el
 * AppServiceProvider y la migración que un candado lee del disco.
 *
 * La raíz de la aplicación se apunta a esa fixture DESPUÉS de arrancar, así
 * que `base_path()` y compañía resuelven ahí y `Candados::todos()` corre con
 * sus valores por omisión, igual que en un sistema real.
 */
abstract class TestCase extends Base
{
    /**
     * Filament y Livewire registran sus proveedores por descubrimiento, como
     * en cualquier sistema; enumerarlos a mano se desactualiza con cada versión.
     */
    protected $enablesPackageDiscoveries = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setBasePath($this->raizDeLaFixture());

        $this->configurarLaAplicacion();
    }

    protected function tearDown(): void
    {
        // Es estado estático del proceso: sin esto se arrastra a otras pruebas.
        TrustProxies::flushState();

        parent::tearDown();
    }

    /**
     * Lo que en un sistema real hacen bootstrap/app.php y el AppServiceProvider.
     */
    protected function configurarLaAplicacion(): void
    {
        // Primero de todos en «web»: ver el docblock del middleware.
        $kernel = $this->app->make(Kernel::class);
        assert($kernel instanceof KernelDeLaravel);
        $kernel->prependMiddlewareToGroup('web', IgnorarCookieDeRecordar::class);

        // Igual que el AppServiceProvider de cada sistema: la lista sale de la
        // configuración, y se declara acá y no en el bootstrap.
        TrustProxies::at($this->app['config']->get('proxies.confiables'));

        // La clase que decide a dónde van las trazas se llama así en todos los
        // sistemas del scaffold. El alias vale para todo el proceso; las pruebas
        // que necesitan la clase que NO cumple la pasan por su nombre.
        if (! class_exists('App\Support\ReporteDeErrores')) {
            class_alias(ReporteDeErrores::class, 'App\Support\ReporteDeErrores');
        }
    }

    protected function raizDeLaFixture(): string
    {
        return (string) realpath(__DIR__.'/Fixtures/Cumple');
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PanelDePruebaProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('auth.providers.users.model', Usuario::class);

        // Lo mismo que config/proxies.php en cada sistema: los rangos privados,
        // que es donde vive el intermediario en Docker y en el servidor.
        $app['config']->set('proxies.confiables', [
            '127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16',
        ]);
    }

    /**
     * La tabla `users` de Laravel, REGISTRADA con el migrador y no corrida a
     * mano: `RefreshDatabase` hace `migrate:fresh`, que borra lo que
     * `loadLaravelMigrations()` hubiera creado antes de que corriera.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(default_migration_path());
    }

    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function () use ($router): void {
            // La portada: cualquier vista pública pregunta si hay sesión, y esa
            // pregunta es la que hacía revivir la sesión desde la cookie.
            $router->get('/', fn (): string => Auth::check() ? 'con sesión' : 'anónimo');

            $router->get('/ingresar', fn (): string => 'pantalla de acceso')
                ->middleware('guest')
                ->name('ingresar');

            $router->post('/ingresar', fn (Request $request) => $this->ingresar($request))
                ->name('ingresar.post');
        });
    }

    /**
     * El acceso fuera del panel tal como cumple la regla: sin segundo
     * argumento en attempt, pida lo que pida el formulario.
     */
    protected function ingresar(Request $request): RedirectResponse
    {
        $credenciales = ['email' => $request->string('email')->toString(), 'password' => $request->string('password')->toString()];

        Auth::attempt($credenciales);

        return redirect('/');
    }
}
