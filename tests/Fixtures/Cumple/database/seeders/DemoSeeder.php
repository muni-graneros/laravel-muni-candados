<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fixture de texto: el seeder de demostración tal como cumple la regla. La
 * pregunta por el entorno va ANTES de la primera contraseña.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        User::updateOrCreate(
            ['email' => 'demo@example.test'],
            ['name' => 'Persona de demostración', 'password' => Hash::make('demo')],
        );
    }
}
