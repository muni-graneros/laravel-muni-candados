<?php

declare(strict_types=1);

namespace Muni\Candados\Tests;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Muni\Candados\Tests\Fixtures\App\Providers\PanelSinCandadoProvider;

/**
 * La aplicación de mentira que NO cumple: un Laravel recién instalado.
 *
 * Sin el middleware que ignora la cookie de recordar —ni en «web» ni en el
 * panel—, con un acceso fuera del panel que honra «remember», y con la raíz
 * apuntada a `tests/Fixtures/NoCumple`, donde el Dockerfile, los seeders, el
 * bootstrap y la migración son los que un candado tiene que rechazar.
 *
 * Es la contraprueba de cada candado: si una comprobación pasa también acá,
 * no está vigilando nada.
 */
abstract class TestCaseNoCumple extends TestCase
{
    protected function configurarLaAplicacion(): void
    {
        // Ni el middleware en «web» ni la lista de proxies desde la
        // configuración ni el alias de la clase que decide el destino de las
        // trazas: cada prueba de esta familia fija lo que quiere romper.
    }

    protected function raizDeLaFixture(): string
    {
        return (string) realpath(__DIR__.'/Fixtures/NoCumple');
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PanelSinCandadoProvider::class];
    }

    /**
     * El acceso que vuelve a repartir la cookie: es EXACTAMENTE lo que el
     * candado «nadie emite la cookie de recordar» busca en el código de un
     * sistema. Vive acá, en la base de las pruebas, y no en un directorio
     * que ese candado revise.
     */
    protected function ingresar(Request $request): RedirectResponse
    {
        $credenciales = ['email' => $request->string('email')->toString(), 'password' => $request->string('password')->toString()];

        Auth::attempt($credenciales, $request->boolean('remember'));

        return redirect('/');
    }
}
