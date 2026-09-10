<?php

declare(strict_types=1);

namespace Muni\Candados\Tests\Fixtures\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * El mismo caso que {@see ModeloDePaquete}, pero con su propia policy
 * registrada a mano: el arreglo real de este candado.
 */
class OtroModeloDePaquete extends Model
{
    protected $table = 'otros_modelos_de_paquete';

    protected $guarded = [];
}
