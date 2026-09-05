<?php

use App\Http\Controllers\AccesoController;
use Illuminate\Support\Facades\Route;

/*
 * Fixture de texto: rutas que no piden la cookie de recordar por ningún lado.
 */
Route::view('/', 'portada');
Route::view('/login', 'auth.ingresar')->middleware('guest')->name('ingresar');
Route::post('/login', [AccesoController::class, 'ingresar'])->middleware('throttle:auth')->name('ingresar.post');
