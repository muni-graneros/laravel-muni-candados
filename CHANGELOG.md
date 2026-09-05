# Cambios

Formato: [Keep a Changelog](https://keepachangelog.com/es/1.1.0/). Versionado semántico.

## [Sin publicar]

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
