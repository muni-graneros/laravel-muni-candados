<?php

declare(strict_types=1);

namespace Muni\Candados\Tests\Fixtures\App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Copia del middleware que tienen los sistemas del ecosistema: la cookie de
 * «Recordarme» se quita de la petición antes de que nadie resuelva al usuario,
 * y se vence en el navegador para que no vuelva mañana.
 *
 * Va PRIMERO en el grupo «web» y PRIMERO en la lista del panel de Filament,
 * que no pasa por «web». Se quita por NOMBRE, así que da lo mismo que el valor
 * todavía venga cifrado: el descifrado de cookies corre después.
 */
class IgnorarCookieDeRecordar
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($this->nombresDeCookieDeRecordar() as $nombre) {
            if (! $request->cookies->has($nombre)) {
                continue;
            }

            $request->cookies->remove($nombre);
            Cookie::queue(Cookie::forget($nombre));
        }

        return $next($request);
    }

    /**
     * @return list<string>
     */
    private function nombresDeCookieDeRecordar(): array
    {
        $nombres = [];

        foreach ((array) config('auth.guards', []) as $guard => $definicion) {
            if (! is_array($definicion) || ($definicion['driver'] ?? null) !== 'session') {
                continue;
            }

            $instancia = Auth::guard((string) $guard);

            if ($instancia instanceof SessionGuard) {
                $nombres[] = $instancia->getRecallerName();
            }
        }

        return $nombres;
    }
}
