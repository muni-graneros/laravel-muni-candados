<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fixture de texto: siembra una credencial conocida sin preguntar nunca por
 * el entorno. En producción es una puerta abierta.
 */
class SinGuardiaSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.test'],
            ['name' => 'Administración', 'password' => Hash::make('secreto')],
        );
    }
}
