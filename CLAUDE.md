# laravel-muni-candados

Paquete Composer privado de la **Municipalidad de Graneros**
(`muni-graneros/laravel-muni-candados`).

## Qué es

Los candados del ecosistema municipal como tests **heredables**: cada candado
prueba una regla que todo sistema tiene que cumplir ("el seeder de demo no
corre en producción", "los errores no salen del país", "nadie emite cookie de
recordar"), escrita una sola vez en vez de copiada byte a byte en 6-8 repos.
Candados actuales en `src/Candados/`: `ErroresNoSalenDelPais`,
`ProxiesDeConfianza`, `CookieDeRecordarInerte`,
`SeedersSinCredencialesEnProduccion`, `NadieEmiteCookieDeRecordar`,
`ImagenDeProduccion`, `GuardaDeCredencialesDePlantilla`,
`PwaSinRestosDelScaffold`, `HigieneDeLaEtapaDeAssets`,
`SinCdnDeFuentesNiIconos`. Los últimos dos —`HigieneDeLaEtapaDeAssets` y
`SinCdnDeFuentesNiIconos`— NO están en `Candados::todos()` todavía: la
adopción real en los sistemas del ecosistema no llegó al 100% cuando se
promovieron, y se registran a mano hasta que estén en los nueve.

## Qué NO es

- No es una librería de utilidades: cada clase en `src/Candados/` es un
  candado con su propio test heredable, no una función de propósito general.
- No prueba funcionalidad de un sistema concreto — prueba una regla
  transversal. Si la necesidad es específica de un sistema, no va acá.
- No incluye los candados de `laravel-muni-acceso` (los de MFA/sesión):
  aparece solo como `suggest`, no como dependencia.

## OJO — fixtures que parecen código real

`tests/Fixtures/Cumple/` y `tests/Fixtures/NoCumple/` son **fixtures de
prueba**: apps mínimas que deliberadamente cumplen o violan cada regla, para
que el candado se pruebe contra ambos casos. Un `grep` o una búsqueda de
patrón sobre el repo (por ejemplo buscando "seeder en producción" o "cookie
de recordar") va a encontrar **falsos positivos** ahí — código que parece la
violación real pero es intencional y vive dentro de `tests/`. Antes de
"corregir" algo que aparece en `Fixtures/NoCumple/`, confirmar que no es a
propósito.

## Versión publicada

Tags en `origin`: `v0.1.0`, `v0.2.0`, `v0.2.1` (última: `v0.2.1`, 2026-09-05,
corrige `GuardaDeCredencialesDePlantilla` para que lea
`vendor/composer/installed.json` en vez de solo `composer.json`).

**Importante sobre los consumidores:** los 8 sistemas municipales + el
scaffold instalan con constraint `^0.1` en `require-dev`. En Composer, `^0.1`
sobre una versión 0.x solo permite `>=0.1.0 <0.2.0` — **no van a recibir
`0.2.0` ni `0.2.1` con un `composer update` normal**, aunque ya estén
publicados. Si una tarea depende de que un consumidor tenga el fix de
`GuardaDeCredencialesDePlantilla` (0.2.1) o el candado nuevo de
`ProxiesDeConfianza` (0.2.0), primero hay que subir el constraint a `^0.2` en
ese `composer.json` — no basta con `composer update`.

## Sesiones en paralelo (Claude Code / Gemini Antigravity / terminal manual)

Varias sesiones de Claude Code y Gemini Antigravity trabajan sobre las mismas
copias de trabajo de `~/Dev`, esta incluida. Antes de tocar nada:
`/home/cesar/Dev/scripts/sesion estado .`. Al empezar: `sesion tomar . "qué vas a
hacer"`. Al terminar: `sesion soltar .`. No bloquea — es un aviso — pero si el
marcador es ajeno, mirá `git log --oneline -5` y `git status` antes de cualquier
`reset`/checkout, y commiteá siempre con `git commit --only -- <rutas>` (el índice
es compartido). Detalle y motivo en `~/Dev/CLAUDE.md`.

## Comandos reales

Hay `Makefile`:

- `make test` → `XDEBUG_MODE=off ./vendor/bin/pest` (cada candado contra su
  fixture que cumple y otra que no, SQLite en memoria).
- `make stan` → `XDEBUG_MODE=off ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress`
  (nivel 8, **sin baseline**).
- `make pint` → comprueba formato; `make pint-fix` lo aplica.
- `make check` → `pint stan test`, el mismo orden que corre el CI.

`post-install-cmd` en `composer.json` fija `core.hooksPath` a `.githooks`.

## Cómo se prueba

`make check` antes de cualquier commit que toque `src/`. Al agregar un
candado nuevo: su propio test en `tests/Candados/`, más una fixture que
cumple y otra que no cumple (siguiendo el patrón de `tests/Fixtures/Cumple`
y `NoCumple`), más una entrada en `tests/Adopcion/TodosTest.php` si aplica.
`stan` corre sin baseline — un candado con un error de tipos no se puede
"pasar por alto" con una excepción en el baseline.

## Cómo se publica

**César hace el push y el tag.** Antes de pedírselo:

1. `make check` en verde.
2. CHANGELOG: mover `[Sin publicar]` a una sección de versión real con fecha.
3. Confirmar si el cambio es un candado nuevo (bump de minor, `0.x.0`) o una
   corrección de uno existente (bump de patch, `0.x.y`) — el historial de
   tags sigue ese criterio (`0.1.0` → `0.2.0` agregó candado, `0.2.0` →
   `0.2.1` corrigió `GuardaDeCredencialesDePlantilla`).

## Consumidores — dónde subir la versión

Los 8 sistemas municipales + el scaffold, todos en `require-dev` con `^0.1`.
Para que reciban `0.2.x`, hay que subir el constraint en el `composer.json`
de cada uno (no es automático). `laravel-muni-acceso` NO es consumidor hoy:
solo aparece como `suggest` en este paquete, en sentido inverso.

## Qué NO hacer acá

- No "arreglar" un fixture de `tests/Fixtures/NoCumple/` para que pase el
  candado — ese fixture existe para fallar a propósito.
- No agregar `phpstan-baseline` para silenciar un error: la regla de este
  repo es nivel 8 sin baseline.
- No relajar un candado existente para que un sistema puntual pase: el
  candado es la regla del ecosistema; si un sistema no cumple, se corrige el
  sistema, no el candado (salvo que la regla misma esté mal, lo que se
  discute con César antes de tocarla).
- No confundir un cambio en `tests/Fixtures/` con un cambio de comportamiento
  real del candado — son cosas distintas y hay que tratarlas por separado en
  el commit.
