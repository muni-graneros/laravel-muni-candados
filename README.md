# laravel-muni-candados

Los candados del ecosistema municipal de Graneros como tests **heredables**.

Un candado no prueba una funcionalidad: prueba una **regla** que todo sistema
del ecosistema tiene que cumplir —«el seeder de demo no corre en producción»,
«los errores no salen del país», «nadie emite cookie de recordar»—, escrita como
test para que ningún sistema pueda desobedecerla en silencio.

## Por qué existe

Estos tests estaban copiados, byte a byte, en entre seis y ocho sistemas
(`licencias`, `discapacidad`, `feria`, `rrhh`, `seguridad`, `control-acceso`,
`web-graneros`, el scaffold…). Cuando la regla cambiaba —el candado de proxies
sumó una comprobación el 3 de septiembre— había que editar ocho repos, y el
que se olvidaba quedaba con la regla vieja sin que nada lo avisara. Con este
paquete la regla vive una vez y cada sistema la hereda con una línea.

## Cómo se adopta

> Publicado en `muni-graneros/laravel-muni-candados`. El repositorio es privado,
> así que la entrada `vcs` va con `"no-api": true`: sin eso Composer resuelve por
> la API de GitHub y pide un token personal.
>
> **La URL va en `https://`, no con el alias SSH.** Poner
> `git@github-graneros:…` funciona en el equipo de César —el alias vive en su
> `~/.ssh/config`— y **rompe el CI**, donde `composer install` muere con
> «Could not resolve hostname github-graneros» antes de instalar nada. Con la URL
> `https://` el `insteadOf` del equipo la reescribe a SSH igual, y el CI puede
> autenticarse con su propia credencial. Es la forma que ya usan `muni-shared` y
> `muni-ui` en los nueve sistemas.

En el `composer.json` del sistema:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/muni-graneros/laravel-muni-candados.git",
            "no-api": true
        }
    ]
}
```

```bash
composer require --dev muni-graneros/laravel-muni-candados:^0.6
```

### El constraint: `^0.6`, no `^0.1`

**En una versión `0.x` el caret NO sube de minor.** `^0.6` significa
`>=0.6.0 <0.7.0`: un sistema con `^0.1` en su `composer.json` se queda en la
`0.1.x` para siempre, y `composer update` nunca le va a traer la `0.6.1` ni
avisarle de que existe. Eso ya pasó: los ocho sistemas y el scaffold quedaron
con `^0.1` mientras el paquete iba por la `0.2.1`, creyendo estar al día y sin
recibir ni el arreglo de `guardaDeCredencialesDePlantilla` ni ningún candado
nuevo.

Cada minor de este paquete (`0.6` → `0.7`) hay que **subirlo a mano** en el
`composer.json` de cada sistema. Un `composer update` no alcanza. Al bajar una
versión, comprobar con `composer show muni-graneros/laravel-muni-candados` qué
quedó instalado de verdad, no lo que promete el constraint.

### El archivo de test en el sistema

Un archivo en `tests/Feature` —tiene que ser `Feature`, porque dos candados
hacen peticiones y uno usa la base—:

```php
<?php
// tests/Feature/CandadosDelEcosistemaTest.php

use Muni\Candados\Candados;

Candados::todos();
```

Los `it()` aparecen en la suite del sistema con sus nombres de siempre, como si
estuvieran escritos ahí. El candado de la cookie aplica `RefreshDatabase` al
archivo por su cuenta.

Los archivos locales que ese `todos()` reemplaza —si el sistema los tenía
copiados— se borran:

```
tests/Feature/SeedersSinCredencialesEnProduccionTest.php
tests/Unit/ImagenDeProduccionTest.php
tests/Feature/ErroresNoSalenDelPaisTest.php
tests/Feature/ProxiesDeConfianzaTest.php
tests/Feature/NadieEmiteCookieDeRecordarTest.php
tests/Feature/CookieDeRecordarInerteTest.php
```

## Qué entra en `todos()` y qué se registra a mano

Este es el dato del que dependen los sistemas host, así que va primero.
`Candados::todos()` registra **ocho** de los once candados. Los otros tres
existen, están probados y hay que escribirlos a mano: se quedaron fuera porque
el día que se promovieron la adopción real en el ecosistema no llegaba al 100%,
y un candado que aparece en rojo el día que se instala se desactiva antes de
arreglarse.

| Candado | ¿En `todos()`? | Desde |
|---|---|---|
| `seedersSinCredencialesEnProduccion` | sí | v0.1.0 |
| `imagenDeProduccion` | sí | v0.1.0 |
| `erroresNoSalenDelPais` | sí | v0.1.0 |
| `proxiesDeConfianza` | sí | v0.1.0 |
| `nadieEmiteCookieDeRecordar` | sí | v0.1.0 |
| `cookieDeRecordarInerte` | sí | v0.1.0 |
| `guardaDeCredencialesDePlantilla` | sí | v0.2.0 |
| `pwaSinRestosDelScaffold` | sí | v0.4.0 |
| `higieneDeLaEtapaDeAssets` | **no** — a mano | existe desde v0.3.0 |
| `sinCdnDeFuentesNiIconos` | **no** — a mano | existe desde v0.4.0 |
| `ningunResourceSinAutorizacion` | **no** — a mano | existe desde v0.6.0 |

Los tres de abajo se agregan al mismo archivo, después de `todos()`:

```php
Candados::todos();

Candados::higieneDeLaEtapaDeAssets();
Candados::sinCdnDeFuentesNiIconos();
Candados::ningunResourceSinAutorizacion();
```

Cuándo se mueve uno a `todos()`: cuando los nueve sistemas lo cumplan. Ese
movimiento es un cambio de minor con su aviso de qué se rompe en el CHANGELOG
—`pwaSinRestosDelScaffold` entró así en la `0.4.0` y puso `seguridad-graneros`
en rojo a propósito—, nunca un parche silencioso.

### Lo que varía por sistema

`todos()` usa los valores que comparten los sistemas generados desde el
scaffold. Si algo se llama distinto, se registran los candados uno por uno:

```php
use Muni\Candados\Candados;

Candados::seedersSinCredencialesEnProduccion();                 // database/seeders
Candados::imagenDeProduccion(imagenBase: 'dunglas/frankenphp'); // Dockerfile en la raíz
Candados::erroresNoSalenDelPais();                              // la clase se resuelve sola, ver abajo
Candados::proxiesDeConfianza(claveDeConfiguracion: 'proxies.confiables');
Candados::nadieEmiteCookieDeRecordar();                         // app/ y routes/
Candados::guardaDeCredencialesDePlantilla();                    // composer.json + vendor/composer/installed.json
Candados::pwaSinRestosDelScaffold(excepciones: ['sw.js' => 'PWA de terreno, ver PwaPatrulleroTest']);
Candados::cookieDeRecordarInerte(
    rutaDeAccesoFuera: 'ingresar',        // se omite la prueba si la ruta no existe
    rutaDeAccesoFueraPost: 'ingresar.post',
    urlDelPanel: null,                    // por omisión se le pregunta a Filament
    crearUsuario: fn () => User::factory()->create(), // con contraseña «password»
);
```

**No hace falta pasarle `clase:` a `erroresNoSalenDelPais`.** Desde la `0.5.2`
resuelve por lo que existe: primero la local (`App\Support\ReporteDeErrores`,
el sistema que todavía tiene la suya manda), después la del paquete compartido
(`Muni\Shared\Errores\ReporteDeErrores`). Fijarla a mano al valor viejo es
justo lo que rompía al adoptar `muni-shared` y borrar la clase local.

Todos los parámetros están documentados en `Muni\Candados\Candados`.

Los candados que solo miran archivos (`imagenDeProduccion`,
`seedersSinCredencialesEnProduccion`, `nadieEmiteCookieDeRecordar`,
`guardaDeCredencialesDePlantilla`, `higieneDeLaEtapaDeAssets`) también se
pueden registrar desde `tests/Unit`, sin arrancar Laravel, pasándoles las rutas.

## Qué vigila cada candado, y por qué

| Candado | Regla | Por qué existe |
|---|---|---|
| `seedersSinCredencialesEnProduccion` | Ningún seeder llama a `Hash::make()` sin preguntar antes por el entorno (`environment('production')` o `isProduction()`), y la pregunta va **antes** de la primera contraseña. | Los usuarios de demostración tienen contraseña conocida y uno es super administrador: en producción son una puerta abierta. Y como se siembran con `updateOrCreate`, borrarlos a mano no alcanza — vuelven en la corrida siguiente. |
| `imagenDeProduccion` | El `FROM` de FrankenPHP lleva versión completa; hay un `HEALTHCHECK` propio, en forma shell, contra `/up` y no contra `:2019`; `curl` está en el `apk add`. | Una etiqueta flotante hace que dos builds del mismo commit den imágenes distintas. El chequeo heredado de la base mira la API admin de Caddy, que responde 200 con Octane caído: el contenedor se declaraba sano con la aplicación muerta. En forma exec no hay shell y el `\|\| exit 1` no decide nada. |
| `erroresNoSalenDelPais` | La clase que decide el destino de las trazas rechaza `sentry.io` (por sufijo de host, no `str_contains`), acepta el GlitchTip municipal, no da por bueno un DSN ilegible, y `bootstrap/app.php` la consulta de verdad. | Una traza lleva ruta, consulta y a veces el cuerpo del request: datos de un vecino. Mandarla a sentry.io es una transferencia internacional de datos personales que la Ley 21.719 no permite sin base de licitud. El enganche con `class_exists` a secas era incondicional. |
| `proxiesDeConfianza` | `TrustProxies` no tiene comodín; la lista sale de la configuración; se declara **una** vez, en el `AppServiceProvider` y no en el bootstrap; y una `X-Forwarded-For` falsa no cambia la IP que ve la aplicación. | Con «*» cualquiera declara la dirección que quiera y evade lo que se cuente por IP (intentos de acceso, límites). El bootstrap corre antes de que la configuración esté cargada: una lista ahí es código muerto que dice otra cosa. |
| `nadieEmiteCookieDeRecordar` | Ni `remember: true`, ni `->boolean('remember')`, ni `attempt($credenciales, …)` con segundo argumento en `app/` o `routes/`. | La batería no ejercita ClaveÚnica, Keycloak ni cada formulario suelto. Se mira el código para que una cookie de catorce meses no reaparezca en silencio. |
| `cookieDeRecordarInerte` | Con la cookie de «Recordarme» y sin sesión, ni la portada, ni el `guest` del login, ni el panel autentican; todos la vencen; el formulario de acceso no la emite aunque la pidan; y la migración que olvida los testigos repartidos sigue vaciando `remember_token`. | El guard vuelve a autenticar en cuanto ve la cookie y escribe el id en la sesión; en la petición siguiente el panel no distingue esa sesión de una abierta con contraseña, y el segundo factor queda de único factor. |
| `guardaDeCredencialesDePlantilla` | El sistema requiere `laravel-muni-shared`, no le apaga el auto-descubrimiento a `Muni\Shared\MuniSharedServiceProvider`, y la versión **instalada** (leída de `vendor/composer/installed.json`) es ≥ 1.19.0. | La guarda que aborta el arranque en producción con las credenciales del `.env.example` la engancha solo el `boot()` de ese proveedor. Mirar el `composer.json` no alcanza: promete, no instala — un sistema con `muni-shared: ^1.18` pasaba las dos primeras comprobaciones y no tenía la guarda. |
| `pwaSinRestosDelScaffold` | Ningún `public/sw*.js` ni `public/manifest*.webmanifest` se sirve sin que una vista lo registre o lo enlace, y todo worker que una vista registre existe de verdad. | Un service worker es el único código que sigue respondiendo con el servidor caído: si cachea una página autenticada, la sirve después sin sesión ni policy. El scaffold repartió uno genérico —que cachea toda respuesta GET 200— en ocho repos; inerte mientras nadie lo registra, y a dos líneas de activarse. En `personas-graneros` ya había pasado. |
| `higieneDeLaEtapaDeAssets` | La etapa `FROM node:… AS assets` del Dockerfile usa `--omit=dev` e `--ignore-scripts`, lo que `vite build` necesita está en `dependencies` (derivado de los `import` reales, no de una lista a mano) y nada de producción se resuelve fuera de `registry.npmjs.org`. | Un `npm ci` a secas instala las `devDependencies` y **ejecuta los `postinstall` de terceros dentro de la imagen**. El de `ffmpeg-static`, que arrastra `demo-engine`, se baja 70 MB desde GitHub durante el build: falló con un 407 detrás de un proxy. El candado exige también la cadena de build del lado correcto, porque `--omit=dev` a secas deja al panel sin CSS. |
| `sinCdnDeFuentesNiIconos` | El panel resuelve su tipografía con `LocalFontProvider` y no con `BunnyFontProvider`; la CSP no nombra `fonts.bunny.net`, `fonts.googleapis.com`, `fonts.gstatic.com` ni `cdnjs.cloudflare.com`; y ningún archivo de `resources/` los carga de verdad. | `->font('Inter')` sin `provider:` hace que Filament le pida la tipografía a `fonts.bunny.net` en cada carga: la IP de cada funcionario a un tercero (Ley 21.719), y el panel sin tipografía en la LAN municipal filtrada. |
| `ningunResourceSinAutorizacion` | Barre `Filament::getPanels()` y, por cada Resource, exige que `Gate::getPolicyFor($resource::getModel())` resuelva algo o que el Resource sobreescriba `canViewAny()` (comprobado con `ReflectionMethod::getDeclaringClass()`). Opcionalmente fija modelo => policy exacta con `politicasExactas`. | `Gate::guessPolicyName()` solo adivina `App\Policies\*` para modelos de `App\Models`: para uno de un vendor (`Spatie\Activitylog\Models\Activity`, `Muni\Shared\Onboarding\OnboardingTour`) nunca encuentra nada. Y sin modo estricto —ninguno de los nueve lo configura— la ausencia de policy **concede**: el archivo de la policy puede existir, su test unitario pasar, y la autorización real nunca consultarlo. |

Sobre la mención de una URL en `resources/`: solo cuenta una URL con esquema o
protocolo-relativa, como la que arma un `<link>`, un `@import` o un
`<script src>` — un comentario que explica que ya no se usa no marca en rojo.

El porqué largo está en el docblock de cada clase en `src/Candados/`.

### El gotcha de `->font()` con Octane

`sinCdnDeFuentesNiIconos` acepta dos arreglos legítimos:

- **La familia es Inter**: se borra la línea `->font()`. Filament ya sirve
  «Inter Variable» self-hosted y sin la llamada resuelve `LocalFontProvider`
  solo. Así quedó en rrhh, seguridad, discapacidad, control-acceso y web.
- **La familia no viene con Filament** (IBM Plex Sans, en feria-graneros): se
  self-hostea con `@fontsource` y se pasa `provider: LocalFontProvider::class`
  explícito.

**Solo en el segundo caso**, el `url:` de `->font()` tiene que ir como
**Closure, no como string ya resuelto**:

```php
->font(
    'IBM Plex Sans',
    url: fn (): string => app(\Illuminate\Foundation\Vite::class)->asset('resources/css/panel-fuente.css'),
    provider: \Filament\FontProviders\LocalFontProvider::class,
)
```

Con Octane el worker vive entre peticiones: `PanelProvider::panel()` corre
UNA vez al arrancar el worker, no en cada request. Si `url:` se resolviera ahí
como string, `app(Vite::class)->asset()` armaría la URL con el host de esa
PRIMERA petición —horneado para siempre—, y todas las peticiones siguientes
la recibirían igual. Con el contenedor expuesto en un puerto y `APP_URL`
apuntando a otro puerto interno, eso pedía la hoja de estilos al puerto
equivocado. Como Closure, Filament la evalúa en cada petición y arma la URL
con el host de ESA petición. El candado no puede comprobar esto —es una
propiedad de runtime, no de forma—, así que queda documentado acá.

## Cuando un candado falla

El mensaje dice qué regla se rompió y dónde (el archivo, la etiqueta, el DSN).
Un candado no se «arregla» en el paquete: se arregla el sistema. Si la regla
cambia para todo el ecosistema, se cambia acá, en su propio commit, y los
sistemas la reciben al actualizar.

Si un archivo que el candado lee no existe (`Dockerfile`, `bootstrap/app.php`,
la migración), el candado **falla** con la ruta que buscó: un archivo que falta
no es «cumple», es o un sistema que perdió algo o un candado mal apuntado.

Un huérfano de PWA es la excepción a «se borra»: `pwaSinRestosDelScaffold`
distingue por SHA-256 el resto real del scaffold (mensaje: bórralo) de un
worker propio con prueba dedicada al que solo le falta el
`serviceWorker.register()` (mensaje: registralo). Si el archivo es intencional
y ninguna de las dos cosas aplica, se exime con
`excepciones: ['sw.js' => 'motivo escrito']` — una excepción sin motivo falla
pidiendo que se escriba por qué.

## Qué expone el paquete

- `Muni\Candados\Candados` — la fachada estática: `todos()` y un método por
  candado. Es lo único que un sistema escribe.
- `Muni\Candados\Candado` — la clase base abstracta. Resuelve la raíz del
  proyecto (`base_path()` con Laravel arrancado, `getcwd()` sin él), lee
  archivos fallando con la ruta buscada y expone el caso de prueba en curso.
  Solo hace falta para escribir un candado nuevo dentro de este repo.
- `Muni\Candados\CasoDePrueba` — el caso de prueba en curso visto por lo que un
  candado necesita (`withCookie()`, `get()`, `markTestSkipped()`).
- `Muni\Candados\Candados\*` — una clase por candado, con sus comprobaciones
  como métodos públicos.

**No hay ServiceProvider, ni config, ni vistas, ni migraciones, ni comandos: no
se publica nada al host.** Es una dependencia de desarrollo (`require-dev`) que
solo se ve dentro de la suite.

## Qué requiere

De `composer.json` (no de memoria):

- `php: ^8.4`
- `laravel/framework: ^13.0`
- `pestphp/pest: ^4.0`

`filament/filament` aparece como `suggest` y como `require-dev`, no como
dependencia: sin Filament, `cookieDeRecordarInerte` y `sinCdnDeFuentesNiIconos`
necesitan que se les pasen la URL del panel y la de acceso a mano, y
`ningunResourceSinAutorizacion` no tiene paneles que barrer.

## Cómo está probado

La suite propia corre cada candado contra dos aplicaciones de mentira montadas
con Testbench:

- `tests/Fixtures/Cumple` + `tests/TestCase.php`: lo que un sistema del
  scaffold tiene. Sobre ella corre `Candados::todos()` con sus valores por
  omisión (`tests/Adopcion/TodosTest.php`) y tiene que quedar todo en verde.
- `tests/Fixtures/NoCumple` + `tests/TestCaseNoCumple.php`: un Laravel recién
  instalado. Sobre ella cada comprobación tiene que **fallar**, con el mensaje
  que se espera (`tests/Candados/*`, `tests/NoCumple/*`). Un candado que pasa
  también acá no vigila nada.

Las comprobaciones son métodos públicos de cada candado precisamente para que
la suite pueda llamarlas y esperar la falla.

Un subtest que recorre una lista vacía no cuenta como verde: en la `0.6.1` se
corrigieron `pwaSinRestosDelScaffold` y `seedersSinCredencialesEnProduccion`,
que en un sistema sin PWA o sin seeder de credenciales quedaban con cero
aserciones (PHPUnit los marcaba *risky*, indistinguibles de un candado que pasa
por buenas razones).

## Desarrollo

```bash
make test    # Pest, SQLite en memoria
make stan    # PHPStan nivel 8, sin baseline
make pint    # formato del ecosistema
make check   # los tres, como el CI
```

Dos trampas de Pest que este paquete esquiva a propósito:

- `expect()->not->…` recorta el mensaje propio al fallar. Los candados usan
  `PHPUnit\Framework\Assert` para que el mensaje llegue entero.
- Un closure creado en contexto estático es estático y Pest no puede hacerle
  `bind` al caso de prueba. Los `it()` se registran desde métodos de instancia,
  y el caso en curso se pide con `caso()` en vez de usar `$this`.

## Qué no hace

- No instala nada en ningún sistema: es una dependencia de desarrollo y no
  publica assets, config ni migraciones.
- No trae los arreglos que vigila, solo los comprueba. Lo que vigila vive en
  otro lado: `ReporteDeErrores` y la guarda de credenciales de plantilla, en
  `laravel-muni-shared`; los middlewares `IgnorarCookieDeRecordar` y
  `RechazarSesionRecordada` y la migración `olvidar_los_testigos_de_recordarme`,
  todavía en cada sistema.
- No reemplaza construir la imagen ni desplegar: las comprobaciones sobre el
  Dockerfile son de forma.
- No incluye los candados de MFA y sesión de `laravel-muni-acceso`.
