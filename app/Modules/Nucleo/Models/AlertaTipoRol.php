<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * M13 · Un rol que recibe un tipo de alerta (plan 1.10, D2). Cada tipo tiene al menos uno (lo cuida `AlertasService`).
 *
 * @property int $id
 * @property int $alerta_tipo_id
 * @property int $rol_id
 */
class AlertaTipoRol extends Model
{
    use ConAuditoria;

    protected $table = 'alerta_tipo_roles';

    protected $fillable = ['alerta_tipo_id', 'rol_id'];
}
