<?php

use App\Http\Controllers\AccesoController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
 * Fixture de texto: una ruta suelta que entra a una persona con la cookie de
 * recordar puesta. La tercera forma que el candado busca.
 */
Route::view('/', 'portada');
Route::view('/login', 'auth.ingresar')->middleware('guest')->name('ingresar');
Route::post('/login', [AccesoController::class, 'ingresar'])->name('ingresar.post');

Route::get('/entrar-como-demo', function () {
    Auth::login(User::where('email', 'demo@example.test')->firstOrFail(), remember: true);

    return redirect('/');
});
