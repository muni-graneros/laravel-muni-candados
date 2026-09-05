<?php

declare(strict_types=1);

use Muni\Candados\Candados;

/*
 * Los candados que solo miran archivos se pueden registrar desde un test que
 * NO arranca Laravel: este archivo corre sobre el TestCase pelado de PHPUnit
 * (ver tests/Pest.php), igual que tests/Unit en los sistemas del ecosistema.
 */
Candados::imagenDeProduccion(dockerfile: dirname(__DIR__).'/Fixtures/Cumple/Dockerfile');

Candados::seedersSinCredencialesEnProduccion(rutaDeSeeders: dirname(__DIR__).'/Fixtures/Cumple/database/seeders');

Candados::nadieEmiteCookieDeRecordar(directorios: [
    dirname(__DIR__).'/Fixtures/Cumple/app',
    dirname(__DIR__).'/Fixtures/Cumple/routes',
]);
