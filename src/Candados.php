<?php

declare(strict_types=1);

namespace Muni\Candados;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Muni\Candados\Candados\CookieDeRecordarInerte;
use Muni\Candados\Candados\ErroresNoSalenDelPais;
use Muni\Candados\Candados\GuardaDeCredencialesDePlantilla;
use Muni\Candados\Candados\HigieneDeLaEtapaDeAssets;
use Muni\Candados\Candados\ImagenDeProduccion;
use Muni\Candados\Candados\NadieEmiteCookieDeRecordar;
use Muni\Candados\Candados\ProxiesDeConfianza;
use Muni\Candados\Candados\PwaSinRestosDelScaffold;
use Muni\Candados\Candados\SeedersSinCredencialesEnProduccion;

/**
 * La puerta de entrada: un método por candado y `todos()` para el caso común.
 *
 * Lo único que un sistema del ecosistema tiene que escribir para heredar todas
 * las reglas es un archivo en `tests/Feature`:
 *
 *     <?php
 *
 *     use Muni\Candados\Candados;
 *
 *     Candados::todos();
 *
 * Cada método acepta lo que varía por sistema (rutas, nombres de clase, claves
 * de configuración); sin argumentos usa los valores que comparten los sistemas
 * generados desde el scaffold municipal.
 */
final class Candados
{
    /**
     * Todos los candados con los valores por omisión del ecosistema.
     */
    public static function todos(): void
    {
        self::seedersSinCredencialesEnProduccion();
        self::imagenDeProduccion();
        self::erroresNoSalenDelPais();
        self::proxiesDeConfianza();
        self::nadieEmiteCookieDeRecordar();
        self::cookieDeRecordarInerte();
        self::guardaDeCredencialesDePlantilla();
        self::pwaSinRestosDelScaffold();
    }

    /**
     * No queda ningún service worker ni manifest servido que nadie registre.
     *
     * @param  string|null  $publico  por omisión `public`
     * @param  string|null  $vistas  por omisión `resources/views`
     */
    public static function pwaSinRestosDelScaffold(?string $publico = null, ?string $vistas = null): void
    {
        (new PwaSinRestosDelScaffold($publico, $vistas))->registrar();
    }

    /**
     * Ningún seeder siembra credenciales sin excluir producción.
     *
     * @param  string|null  $rutaDeSeeders  por omisión `database/seeders`
     */
    public static function seedersSinCredencialesEnProduccion(?string $rutaDeSeeders = null): void
    {
        (new SeedersSinCredencialesEnProduccion($rutaDeSeeders))->registrar();
    }

    /**
     * La imagen que se publica en el VPS está fijada, sabe decir si está sana
     * y trae con qué comprobarlo.
     *
     * @param  string|null  $dockerfile  por omisión `Dockerfile` en la raíz
     * @param  string  $imagenBase  la imagen de la que parte el Dockerfile
     */
    public static function imagenDeProduccion(?string $dockerfile = null, string $imagenBase = 'dunglas/frankenphp'): void
    {
        (new ImagenDeProduccion($dockerfile, $imagenBase))->registrar();
    }

    /**
     * Las trazas de excepción no se van a un servicio en el extranjero.
     *
     * @param  string  $clase  la clase que decide si el DSN es de un destino propio
     * @param  string  $metodo  su método estático, que devuelve bool
     * @param  string|null  $bootstrap  por omisión `bootstrap/app.php`
     * @param  list<string>|null  $dsnAjenos  DSN que tienen que rechazarse
     * @param  list<string>|null  $dsnPropios  DSN que tienen que aceptarse
     * @param  list<string>|null  $dsnIlegibles  DSN que no se dan por buenos
     */
    public static function erroresNoSalenDelPais(
        string $clase = 'App\\Support\\ReporteDeErrores',
        string $metodo = 'vaADestinoPropio',
        ?string $bootstrap = null,
        ?array $dsnAjenos = null,
        ?array $dsnPropios = null,
        ?array $dsnIlegibles = null,
    ): void {
        (new ErroresNoSalenDelPais($clase, $metodo, $bootstrap, $dsnAjenos, $dsnPropios, $dsnIlegibles))->registrar();
    }

    /**
     * Solo se confía en los proxies configurados, y se declaran una sola vez.
     *
     * @param  string  $claveDeConfiguracion  por omisión `proxies.confiables`
     * @param  string|null  $bootstrap  por omisión `bootstrap/app.php`
     * @param  string|null  $proveedor  por omisión `app/Providers/AppServiceProvider.php`
     */
    public static function proxiesDeConfianza(
        string $claveDeConfiguracion = 'proxies.confiables',
        ?string $bootstrap = null,
        ?string $proveedor = null,
    ): void {
        (new ProxiesDeConfianza($claveDeConfiguracion, $bootstrap, $proveedor))->registrar();
    }

    /**
     * Ningún código del sistema vuelve a pedir la cookie de recordar.
     *
     * @param  list<string>|null  $directorios  por omisión `app/` y `routes/`
     */
    public static function nadieEmiteCookieDeRecordar(?array $directorios = null): void
    {
        (new NadieEmiteCookieDeRecordar($directorios))->registrar();
    }

    /**
     * La cookie de «Recordarme» no autentica en NINGUNA ruta.
     *
     * @param  string|null  $migracion  ruta de la migración que olvida los testigos
     * @param  string|null  $urlDelPanel  por omisión la del panel de Filament
     * @param  string|null  $urlDeAcceso  por omisión la de acceso del panel de Filament
     * @param  string  $rutaDeAccesoFuera  nombre de la ruta de la pantalla de acceso fuera del panel
     * @param  string  $rutaDeAccesoFueraPost  nombre de la ruta que recibe ese formulario
     * @param  string  $guard  el guard de sesión
     * @param  (Closure(): Model)|null  $crearUsuario  cómo crear una persona que pueda entrar (con contraseña «password»)
     * @param  array<string, mixed>  $configuracion  configuración que se fija antes de cada prueba
     * @param  string  $portada  la ruta pública que cualquiera visita
     */
    public static function cookieDeRecordarInerte(
        ?string $migracion = null,
        ?string $urlDelPanel = null,
        ?string $urlDeAcceso = null,
        string $rutaDeAccesoFuera = 'ingresar',
        string $rutaDeAccesoFueraPost = 'ingresar.post',
        string $guard = 'web',
        ?Closure $crearUsuario = null,
        array $configuracion = ['mfa.enabled' => true],
        string $portada = '/',
    ): void {
        (new CookieDeRecordarInerte(
            $migracion,
            $urlDelPanel,
            $urlDeAcceso,
            $rutaDeAccesoFuera,
            $rutaDeAccesoFueraPost,
            $guard,
            $crearUsuario,
            $configuracion,
            $portada,
        ))->registrar();
    }

    /**
     * El sistema requiere `laravel-muni-shared` y no le apaga el auto-descubrimiento a su
     * proveedor: solo así queda enganchada la guarda de credenciales de plantilla que ese
     * paquete engancha sola en su `boot()`.
     *
     * @param  string|null  $composerJson  por omisión `composer.json` en la raíz
     */
    public static function guardaDeCredencialesDePlantilla(?string $composerJson = null): void
    {
        (new GuardaDeCredencialesDePlantilla($composerJson))->registrar();
    }

    /**
     * La etapa Node del Dockerfile no instala devDependencies ni ejecuta los
     * `postinstall` de terceros dentro de la imagen de producción.
     *
     * **No está en `todos()` todavía, a propósito.** Cuando se promovió (06-09)
     * lo cumplía uno solo de los ocho sistemas: meterlo en `todos()` habría
     * puesto en rojo siete suites a la vez, y un candado que aparece rojo el día
     * que se instala se desactiva antes de arreglarse. Cada sistema lo registra
     * a mano al cerrar su Dockerfile; cuando los ocho estén, se mueve a `todos()`
     * y esto se borra.
     *
     * @param  list<string>  $herramientas  lo que la cadena de build necesita sí o sí, además de lo que se deriva de los imports
     * @param  list<string>  $soloDeEscritorio  paquetes que NO pueden estar en `dependencies`
     */
    public static function higieneDeLaEtapaDeAssets(
        ?string $dockerfile = null,
        ?string $packageJson = null,
        ?string $packageLock = null,
        array $herramientas = ['vite', 'laravel-vite-plugin'],
        array $soloDeEscritorio = ['demo-engine'],
        string $entradasJs = 'resources/js/*.js',
    ): void {
        (new HigieneDeLaEtapaDeAssets(
            $dockerfile,
            $packageJson,
            $packageLock,
            $herramientas,
            $soloDeEscritorio,
            $entradasJs,
        ))->registrar();
    }
}
