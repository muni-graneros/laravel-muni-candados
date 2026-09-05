<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Fixture de texto: el acceso que vuelve a repartir la cookie de catorce
 * meses. Dos de las formas que el candado busca, en un solo archivo.
 */
class AccesoController extends Controller
{
    public function ingresar(Request $request): RedirectResponse
    {
        $credenciales = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credenciales, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Las credenciales no coinciden.']);
        }

        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}
