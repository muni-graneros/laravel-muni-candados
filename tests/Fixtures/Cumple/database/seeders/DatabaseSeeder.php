<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Fixture de texto: un seeder que no crea credenciales. Al candado no le
 * interesa.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoSeeder::class,
        ]);
    }
}
