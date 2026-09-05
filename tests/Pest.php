<?php

declare(strict_types=1);

use Muni\Candados\Tests\TestCase;
use Muni\Candados\Tests\TestCaseNoCumple;

/*
|--------------------------------------------------------------------------
| Qué aplicación de mentira corre cada carpeta
|--------------------------------------------------------------------------
|
| `Adopcion/` y `Candados/` corren sobre la que CUMPLE; `NoCumple/` sobre la
| que no. `Unit/` no levanta Laravel: prueba que los candados de archivos se
| puedan registrar desde un test que no arranca la aplicación.
|
*/

pest()->extend(TestCase::class)->in('Adopcion', 'Candados');
pest()->extend(TestCaseNoCumple::class)->in('NoCumple');
