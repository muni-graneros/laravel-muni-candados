<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copia de la migración de los sistemas del ecosistema: olvida de una vez los
 * testigos de «Recordarme» repartidos hasta hoy. El candado la ejecuta contra
 * la base de pruebas y comprueba que ningún testigo viejo siga identificando
 * a nadie.
 *
 * Vaciar la columna no cierra ninguna sesión abierta: la sesión vive en su
 * propio almacén. Lo único que deja de funcionar es entrar sin contraseña.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'remember_token')) {
            return;
        }

        DB::table('users')->whereNotNull('remember_token')->update(['remember_token' => null]);
    }

    /**
     * No hay vuelta atrás: los testigos viejos no se guardaron en ningún lado
     * antes de borrarlos, que es justamente el punto.
     */
    public function down(): void {}
};
