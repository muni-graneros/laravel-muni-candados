# Cambios

Formato: [Keep a Changelog](https://keepachangelog.com/es/1.1.0/). Versionado semántico.

## [Sin publicar]

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
