<?php

declare(strict_types=1);

use Muni\Candados\Candados;

/*
 * La adopción tal cual la escribe un sistema del ecosistema: una línea.
 *
 * Corre sobre la aplicación de mentira que CUMPLE (tests/Fixtures/Cumple y el
 * TestCase que la arma), con los valores por omisión de cada candado —los
 * mismos que usan los sistemas generados desde el scaffold—, así que todo lo
 * que registra tiene que quedar en verde y sin un solo test omitido. La
 * contraprueba de cada candado está en tests/Candados y tests/NoCumple.
 */
Candados::todos();
