<?php

declare(strict_types=1);

namespace Muni\Candados\Tests\Fixtures\App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Como la UserFactory de Laravel: contraseña «password» y un testigo de
 * recordar ya repartido, que es el caso que los candados tienen que vencer.
 *
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Persona de prueba',
            'email' => 'persona-'.Str::lower(Str::random(10)).'@example.test',
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }
}
