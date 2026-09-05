<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fixture de texto: pregunta por el entorno, pero DESPUÉS de haber sembrado
 * la primera contraseña. La guardia llega tarde.
 */
class GuardiaTardiaSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'demo@example.test'],
            ['name' => 'Persona de demostración', 'password' => Hash::make('demo')],
        );

        if (app()->environment('production')) {
            return;
        }

        User::updateOrCreate(
            ['email' => 'otra@example.test'],
            ['name' => 'Otra persona', 'password' => Hash::make('demo')],
        );
    }
}
