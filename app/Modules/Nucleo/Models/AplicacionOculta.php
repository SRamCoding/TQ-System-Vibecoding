<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Una aplicación de Techquk que la persona sacó de su panel (plan 1.4p, D7). Volver a mostrarla borra la fila. Solo la
 * escribe AplicacionesService.
 *
 * @property int $id
 * @property int $user_id
 * @property int $aplicacion_id
 */
class AplicacionOculta extends Model
{
    use ConAuditoria;

    protected $table = 'aplicaciones_ocultas';

    protected $fillable = ['user_id', 'aplicacion_id'];
}
