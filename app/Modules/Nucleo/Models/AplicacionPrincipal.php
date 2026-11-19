<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Una de las 3 aplicaciones principales de una persona (plan 1.4x, D1): de Techquk o propia, nunca las dos, en la
 * posición 1, 2 o 3. Solo la escribe AplicacionesService::ponerPrincipal.
 *
 * @property int $id
 * @property int $user_id
 * @property int $posicion
 * @property ?int $aplicacion_id
 * @property ?int $aplicacion_propia_id
 */
class AplicacionPrincipal extends Model
{
    use ConAuditoria;

    protected $table = 'aplicaciones_principales';

    protected $fillable = ['user_id', 'posicion', 'aplicacion_id', 'aplicacion_propia_id'];

    protected function casts(): array
    {
        return ['posicion' => 'integer'];
    }
}
