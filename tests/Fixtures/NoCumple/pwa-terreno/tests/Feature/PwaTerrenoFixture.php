<?php

declare(strict_types=1);

/*
 * Fixture: NO termina en «Test.php» a propósito, para que PHPUnit no la
 * levante como prueba real de este paquete (el testsuite de phpunit.xml
 * escanea toda `tests/` con el sufijo por omisión). Sirve solo como texto
 * que el candado lee para saber que sw.js tiene una prueba dedicada — imita
 * tests/Feature/PwaPatrulleroTest.php de seguridad-graneros.
 */
it('el service worker de terreno existe', function () {
    expect(file_exists(public_path('sw.js')))->toBeTrue();
});
