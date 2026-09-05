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

> Publicado el 05-09-2026 como `v0.1.0` en
> `git@github-graneros:muni-graneros/laravel-muni-candados.git`. El repositorio
> es privado, así que la entrada `vcs` va con `"no-api": true`: sin eso Composer
> resuelve por la API de GitHub y pide un token personal.

En el `composer.json` del sistema:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github-graneros:muni-graneros/laravel-muni-candados.git",
            "no-api": true
        }
    ]
}
```

```bash
composer require --dev muni-graneros/laravel-muni-candados:^0.1
```

Un archivo en `tests/Feature` —tiene que ser `Feature`, porque dos candados
hacen peticiones y uno usa la base—:

```php
<?php
// tests/Feature/CandadosDelEcosistemaTest.php

use Muni\Candados\Candados;

Candados::todos();
```

Y borrar los seis archivos que reemplaza:

```
tests/Feature/SeedersSinCredencialesEnProduccionTest.php
tests/Unit/ImagenDeProduccionTest.php
tests/Feature/ErroresNoSalenDelPaisTest.php
tests/Feature/ProxiesDeConfianzaTest.php
tests/Feature/NadieEmiteCookieDeRecordarTest.php
tests/Feature/CookieDeRecordarInerteTest.php
```

Los `it()` aparecen en la suite del sistema con sus nombres de siempre, como si
estuvieran escritos ahí. El candado de la cookie aplica `RefreshDatabase` al
archivo por su cuenta.

### Lo que varía por sistema

`todos()` usa los valores que comparten los sistemas generados desde el
scaffold. Si algo se llama distinto, se registran los candados uno por uno:

```php
use Muni\Candados\Candados;

Candados::seedersSinCredencialesEnProduccion();               // database/seeders
Candados::imagenDeProduccion(imagenBase: 'dunglas/frankenphp'); // Dockerfile en la raíz
Candados::erroresNoSalenDelPais(clase: App\Support\ReporteDeErrores::class);
Candados::proxiesDeConfianza(claveDeConfiguracion: 'proxies.confiables');
Candados::nadieEmiteCookieDeRecordar();                        // app/ y routes/
Candados::cookieDeRecordarInerte(
    rutaDeAccesoFuera: 'ingresar',        // se omite la prueba si la ruta no existe
    rutaDeAccesoFueraPost: 'ingresar.post',
    urlDelPanel: null,                    // por omisión se le pregunta a Filament
    crearUsuario: fn () => User::factory()->create(), // con contraseña «password»
);
```

Todos los parámetros están documentados en `Muni\Candados\Candados`.

Los candados que solo miran archivos (`imagenDeProduccion`,
`seedersSinCredencialesEnProduccion`, `nadieEmiteCookieDeRecordar`) también se
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

El porqué largo está en el docblock de cada clase en `src/Candados/`.

## Cuando un candado falla

El mensaje dice qué regla se rompió y dónde (el archivo, la etiqueta, el DSN).
Un candado no se «arregla» en el paquete: se arregla el sistema. Si la regla
cambia para todo el ecosistema, se cambia acá, en su propio commit, y los
sistemas la reciben al actualizar.

Si un archivo que el candado lee no existe (`Dockerfile`, `bootstrap/app.php`,
la migración), el candado **falla** con la ruta que buscó: un archivo que falta
no es «cumple», es o un sistema que perdió algo o un candado mal apuntado.

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

- No instala nada en ningún sistema: es una dependencia de desarrollo.
- No trae los arreglos que vigila. `App\Support\ReporteDeErrores`, los
  middlewares `IgnorarCookieDeRecordar` y `RechazarSesionRecordada` y la
  migración `olvidar_los_testigos_de_recordarme` siguen en cada sistema
  (también idénticos entre ellos: candidatos a un paquete de código, no de
  tests).
- No reemplaza construir la imagen ni desplegar: las comprobaciones sobre el
  Dockerfile son de forma.
