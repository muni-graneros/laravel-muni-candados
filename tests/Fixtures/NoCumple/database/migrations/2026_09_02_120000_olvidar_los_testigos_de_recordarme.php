<?php

use Illuminate\Database\Migrations\Migration;

/**
 * La migración que existe con el nombre correcto pero no olvida nada: los
 * testigos repartidos antes del arreglo siguen valiendo en la tabla, y con
 * ellos la cookie sigue sirviendo por cualquier camino que se le escape al
 * middleware.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Se dejó vacía «para no borrar datos». Es justamente lo que había que borrar.
    }

    public function down(): void {}
};
