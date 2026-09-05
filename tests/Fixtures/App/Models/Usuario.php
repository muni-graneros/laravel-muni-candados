<?php

declare(strict_types=1);

namespace Muni\Candados\Tests\Fixtures\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * El modelo de usuario de la aplicación de mentira, sobre la tabla `users`
 * que Testbench crea con las migraciones de Laravel.
 */
class Usuario extends Authenticatable
{
    /** @use HasFactory<UsuarioFactory> */
    use HasFactory;

    protected $table = 'users';

    protected $guarded = [];

    protected static function newFactory(): UsuarioFactory
    {
        return UsuarioFactory::new();
    }
}
