<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Fixture de texto: el acceso fuera del panel que cumple. `attempt` sin
 * segundo argumento, pida lo que pida el formulario.
 */
class AccesoController extends Controller
{
    public function ingresar(Request $request): RedirectResponse
    {
        $credenciales = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credenciales)) {
            return back()->withErrors(['email' => 'Las credenciales no coinciden.']);
        }

        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}
