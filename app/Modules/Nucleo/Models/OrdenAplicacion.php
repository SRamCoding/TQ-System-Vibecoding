<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * La posición de una aplicación en la cuadrícula de una persona, dentro de su sección (plan 1.4z, D10): de Techquk o
 * propia, nunca las dos. Solo la escribe AplicacionesService::mover.
 *
 * @property int $id
 * @property int $user_id
 * @property ?int $aplicacion_id
 * @property ?int $aplicacion_propia_id
 * @property int $posicion
 */
class OrdenAplicacion extends Model
{
    use ConAuditoria;

    protected $table = 'aplicaciones_orden';

    protected $fillable = ['user_id', 'aplicacion_id', 'aplicacion_propia_id', 'posicion'];

    protected function casts(): array
    {
        return ['posicion' => 'integer'];
    }
}
