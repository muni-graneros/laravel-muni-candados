# Cambios

Formato: [Keep a Changelog](https://keepachangelog.com/es/1.1.0/). Versionado semántico.

## [Sin publicar]

_Nada todavía._

## [0.6.0] - 2026-09-10

### Agregado

- `ningunResourceSinAutorizacion`: ningún Resource de Filament queda sin
  autorización real. Cierra la clase de agujero que ya
  mordió tres veces (`control-acceso`, `discapacidad`, `licencias`) y volvió a
  aparecer con `OnboardingTourPolicy` al adoptar el paquete compartido —
  `Gate::guessPolicyName()` solo adivina `App\Policies\*` para modelos que
  viven en `App\Models`, y para un modelo de un vendor
  (`Spatie\Activitylog\Models\Activity`, o un modelo de `muni-shared`) nunca
  encuentra nada. Sin modo estricto (ninguno de los nueve sistemas lo
  configura), la ausencia de policy resuelve en `Response::allow()`
  (`vendor/filament/filament/src/helpers.php`, `get_authorization_response()`):
  el archivo de la policy puede existir, su test unitario puede pasar, y la
  autorización real nunca lo consulta.
  - Barrido automático, sin lista que nadie mantenga: recorre
    `Filament::getPanels()` y por cada Resource exige que
    `Gate::getPolicyFor($resource::getModel())` resuelva algo, o que el propio
    Resource sobreescriba `canViewAny()` (comprobado con
    `ReflectionMethod::getDeclaringClass()`, para no marcar en rojo a quien
    autoriza a mano y a propósito).
  - Lista explícita opcional (`politicasExactas`, modelo => policy) para fijar
    la clase exacta que un modelo puntual tiene que resolver, más allá del
    barrido general — mismo patrón que `herramientas:` en
    `higienieDeLaEtapaDeAssets` y `clase:` en `erroresNoSalenDelPais`.

### Qué NO hace todavía

- **No está en `Candados::todos()`.** No hay medición de cuántos de los nueve
  sistemas tienen hoy algún Resource sobre un modelo de paquete sin
  `Gate::policy()` explícito — es exactamente el hueco que este candado busca,
  así que es probable que aparezca en varios a la vez. Meterlo en `todos()` a
  ciegas podría poner varias suites en rojo de golpe, lo mismo que pasó con
  `higieneDeLaEtapaDeAssets` y con `sinCdnDeFuentesNiIconos`. Cada sistema lo
  registra a mano con `Candados::ningunResourceSinAutorizacion()` en su
  `tests/Feature/CandadosTest.php` hasta que se mida la adopción real; cuando
  se mida, se decide si se mueve a `todos()`.

## [0.5.2] - 2026-09-07

> **La `v0.5.1` no existe como versión: apunta al mismo commit que la `v0.5.0`.**
> El tag se empujó por error —un `git tag` quedó fuera del encadenado y corrió
> aunque el commit se hubiera cancelado por PHPStan—. No se borra del remoto,
> porque reescribir un tag ya publicado es peor que dejarlo: quien resuelva
> `^0.5` va a tomar esta, que sí trae el arreglo.


### Arreglado

- `erroresNoSalenDelPais` tenía como valor por omisión la clase LOCAL
  (`App\Support\ReporteDeErrores`). En cuanto un sistema adoptaba
  `Muni\Shared\Errores\ReporteDeErrores` y borraba la suya, `Candados::todos()`
  fallaba señalando una clase recién borrada, y ese sistema tenía que **dejar de
  usar `todos()`** y registrar los ocho candados a mano — lo contrario de lo que
  promete este paquete. Pasó de verdad al adoptar en `rrhh-graneros` y en
  `feria-graneros`.
  - Ahora la clase se resuelve **por lo que existe**: primero la local (el
    sistema que todavía tiene la suya manda), después la del paquete. Si no
    existe ninguna, falla nombrando las dos. Pasar `clase:` a mano sigue
    ganando sobre todo.

### Al adoptar, ojo con esto (no es del paquete, es de Laravel)

- **Borrar `ActivityPolicy` y `OnboardingTourPolicy` las deja sin dueño.** El
  adivinador de políticas de Laravel busca `App\Policies\{Modelo}Policy`; para
  un modelo de un vendor —`Spatie\Activitylog\Models\Activity`— eso solo
  funciona mientras el archivo local exista. Al adoptar hay dos salidas
  probadas: dejar la clase local como **fachada** que extiende la del paquete
  (lo que hizo `rrhh-graneros`), o borrarla y registrar la del paquete con un
  `Gate::policy()` explícito en el `AppServiceProvider` (lo que hizo
  `feria-graneros`). Lo que NO funciona es borrarla y confiar en la convención.
  La `RolePolicy` es aparte y más estricta: `filament-shield` la busca por ruta
  hardcodeada, así que ahí la fachada es obligatoria.

## [0.5.0] - 2026-09-07

### Arreglado

- `pwaSinRestosDelScaffold` daba el mismo mensaje («Bórralo, o regístralo con
  su scope acotado si de verdad hace falta») a CUALQUIER `sw*.js` o
  `manifest*.webmanifest` huérfano, sin distinguir el resto real del scaffold
  de código construido a propósito al que solo le falta activarse. El
  CHANGELOG de 0.4.0 (arriba) ya decía, sobre este mismo candado: «El arreglo
  en `seguridad` es borrar los dos archivos» — y eso era un falso positivo:
  `public/sw.js` en `seguridad-graneros` es la PWA de terreno del patrullero
  (GPS, cola offline, botón de pánico), con
  `tests/Feature/PwaPatrulleroTest.php` encima y dos auditorías de seguridad;
  lo único que le faltaba era la línea de `serviceWorker.register()`. Un
  agente que siguiera esa nota al pie de la letra habría tirado trabajo
  probado.
  - El candado ahora distingue los dos casos con evidencia: si el archivo
    servido calza BYTE A BYTE (mismo SHA-256) con el que reparte
    `scaffold-laravel-filament-pwa`, es certeza de que es el resto y el
    mensaje sigue diciendo que se borra; si no calza pero existe una prueba
    dedicada en `tests/` que lo ejercita, el mensaje dice que falta el
    registro y **no** sugiere borrar nada; sin evidencia en ningún sentido,
    el mensaje no empuja a ninguna de las dos acciones.
  - `PwaSinRestosDelScaffold` (y `Candados::pwaSinRestosDelScaffold()`) suman
    dos parámetros: `$tests` (dónde buscar la prueba dedicada, por omisión
    `tests`) y `$excepciones` (`array<string, string>`, nombre de archivo =>
    motivo escrito por el que se lo exime — una excepción sin motivo falla
    pidiendo que se escriba por qué).
  - Verificado contra los dos casos reales: en `seguridad-graneros`,
    `public/sw.js` ahora falla con «No lo borres: registralo con
    `navigator.serviceWorker.register('/sw.js')`…» en vez de «Bórralo»; en
    `licencias-graneros`, donde el worker sí se registra
    (`resources/views/partials/pwa.blade.php`), el candado sigue pasando sin
    cambios.
## [0.4.0] - 2026-09-06

### Agregado

- `sinCdnDeFuentesNiIconos`: el panel Filament resuelve su tipografía con
  `LocalFontProvider` y no con `BunnyFontProvider`; la CSP no nombra
  `fonts.bunny.net`, `fonts.googleapis.com`, `fonts.gstatic.com` ni
  `cdnjs.cloudflare.com`; y ningún archivo de `resources/` los carga de
  verdad (una mención en un comentario que explica que ya no se usa no
  cuenta). Generaliza el candado que se copió, byte a byte, en seis sistemas
  (`feria`, `rrhh`, `seguridad`, `discapacidad`, `control-acceso`,
  `licencias`) tras descubrirse que `->font('Inter')` sin `provider:` hace
  que Filament pida la tipografía a `fonts.bunny.net` en cada carga del
  panel —la IP de cada funcionario a un tercero, Ley 21.719— y deja el panel
  sin tipografía en la LAN municipal filtrada. Dos arreglos legítimos pasan
  el candado: borrar la línea `->font()` cuando la familia es Inter (Filament
  ya la sirve self-hosted), o self-hostear con `@fontsource` y pasar
  `provider: LocalFontProvider::class` cuando no lo es (IBM Plex Sans, en
  feria-graneros). El gotcha de Octane sobre el `url:` de ese segundo caso
  —tiene que ir como Closure, no como string ya resuelto, o el worker
  hornea el host de la primera petición para todas las que siguen— queda
  documentado en el README: un test de forma no lo puede comprobar.

### Qué NO hace todavía

- **No está en `Candados::todos()`.** El día que se promovió, de los nueve
  sistemas del ecosistema solo seis tenían el arreglo real; meterlo en
  `todos()` habría puesto tres suites en rojo de golpe. Cada sistema lo
  registra a mano; cuando los nueve estén, se mueve a `todos()` y esta nota
  se borra.

- `pwaSinRestosDelScaffold`: ningún `public/sw*.js` ni `public/manifest*.webmanifest`
  se sirve si ninguna vista lo registra o lo enlaza, y todo worker que una vista
  registre existe de verdad. Cierra la «PWA fantasma» que el scaffold repartió en ocho
  repos: un service worker que cachea toda respuesta GET con estado 200, sin mirar la
  ruta ni la autenticación. Mientras nadie lo registra es inerte —por eso sobrevivió
  sin que nadie lo notara—, pero basta copiar dos líneas de `serviceWorker.register`
  de otro sistema para que el panel entero empiece a escribirse en el disco del equipo,
  donde queda tras cerrar sesión. En `personas-graneros` eso ya había pasado de verdad.

### Qué se rompe al subir

- **`pwaSinRestosDelScaffold` SÍ entra en `Candados::todos()`**, así que todo sistema
  que suba a esta versión lo hereda sin escribir una línea. Medido hoy sobre los ocho:
  siete pasan —cuatro no tienen `sw.js` y tres lo registran de verdad— y
  **`seguridad-graneros` se pone en rojo**: tiene `public/sw.js` y
  `public/manifest.webmanifest` y ninguna vista los registra. Ese rojo es correcto y es
  el motivo del candado: un service worker que nadie registra hoy es inerte, pero está
  servido y a dos líneas de que alguien lo active copiando un `serviceWorker.register`
  de otro sistema — y entonces empieza a cachear el panel autenticado en el disco del
  funcionario. El arreglo en `seguridad` es borrar los dos archivos, no silenciar el
  candado.
- `sinCdnDeFuentesNiIconos` **no** entra en `todos()` (ver arriba): quien lo quiera lo
  registra a mano.

## [0.3.0] - 2026-09-06

### Agregado

- `higieneDeLaEtapaDeAssets`: la etapa `FROM node:… AS assets` del Dockerfile no
  instala `devDependencies` ni ejecuta los `postinstall` de terceros dentro de la
  imagen de producción, lo que necesita `vite build` está en `dependencies`, y
  nada de lo que se instala en producción se resuelve fuera de
  `registry.npmjs.org`. Nació en `rrhh-graneros` tras un incidente real —el
  `postinstall` de `ffmpeg-static` se bajaba 70 MB desde GitHub durante el build,
  y falló con un 407 detrás de un proxy— y se promovió al comprobar que los ocho
  sistemas tienen la misma etapa y solo ese la vigilaba.
- La lista de herramientas que exige en `dependencies` **se deriva de los
  `import` reales** de `vite.config.js` y de los entrypoints de JS, no de una
  lista escrita a mano: así sirve igual con Tailwind 3 y postcss que con Tailwind
  4 y su plugin de Vite.

### Qué NO hace todavía

- **No está en `Candados::todos()`.** El día que se promovió lo cumplía uno solo
  de los ocho sistemas; meterlo en `todos()` habría puesto siete suites en rojo a
  la vez, y un candado que aparece rojo el día que se instala se desactiva antes
  de arreglarse. Cada sistema lo registra a mano al cerrar su Dockerfile. Cuando
  los ocho estén, se mueve a `todos()` — y ese cambio sí será una versión menor
  con su aviso de qué se rompe.

## [0.2.1] - 2026-09-05

### Corregido

- `guardaDeCredencialesDePlantilla` pasaba con `muni-shared` 1.18 instalado, donde
  la guarda **no existe**: miraba solo el `composer.json`, que promete pero no
  instala. Ahora lee `vendor/composer/installed.json` y exige 1.19.0 o superior.
  Un candado que pasa cuando lo que vigila no existe da por cubierto lo que está
  descubierto, que es peor que no tenerlo.

## [0.2.0] - 2026-09-05

### Agregado

- `guardaDeCredencialesDePlantilla`: comprueba que el sistema requiera
  `laravel-muni-shared` y no le apague el auto-descubrimiento a su proveedor,
  que es lo único que puede dejar sin enganchar la guarda que aborta el arranque
  en producción con las credenciales del `.env.example`. Va en `todos()`: la
  regla es del ecosistema entero. Exige `muni-shared` ≥ 1.19.0, que es donde
  vive la guarda.

## [0.1.0] - 2026-09-05

### Agregado

- Seis candados heredables, con `Candados::todos()` como adopción de una línea y
  un método por candado para lo que varía por sistema:
  `seedersSinCredencialesEnProduccion`, `imagenDeProduccion`,
  `erroresNoSalenDelPais`, `proxiesDeConfianza`, `nadieEmiteCookieDeRecordar`
  y `cookieDeRecordarInerte`.
- Suite propia con Testbench: cada candado contra una aplicación de mentira que
  cumple (`tests/Fixtures/Cumple`) y otra que no (`tests/Fixtures/NoCumple`),
  para que se pruebe que detecta y no solo que pasa.
- `CasoDePrueba`: el caso de prueba en curso visto por lo que un candado
  necesita (peticiones), sin exigir una clase base concreta —sirve con el
  TestCase de Laravel y con el de Testbench—.

### Distinto de los tests que reemplaza

- Los mensajes de fallo van por `PHPUnit\Framework\Assert` y no por
  `expect()->not->…`: Pest recorta el mensaje propio en las expectativas
  negadas, y el mensaje es lo único que le dice a quien lo lee qué regla rompió.
- El candado de seeders nombra los archivos en el mensaje, no solo en el diff.
- El candado de proxies incorpora la comprobación más reciente del ecosistema
  (web-graneros, 2026-09-03): la lista se declara UNA vez, desde la
  configuración, y no en `bootstrap/app.php`.
