<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * M13 · Un hecho que ya se avisó (plan 1.10, D1), una sola vez por su clave: un reintento no lo repite. A quién le llegó
 * está en `notificaciones`.
 *
 * @property int $id
 * @property int $alerta_tipo_id
 * @property string $clave
 * @property ?string $entidad_type
 * @property ?int $entidad_id
 * @property Carbon $emitida_at
 */
class AlertaEmitida extends Model
{
    use ConAuditoria;

    protected $table = 'alertas_emitidas';

    protected $fillable = ['alerta_tipo_id', 'clave', 'entidad_type', 'entidad_id'];

    protected function casts(): array
    {
        return ['emitida_at' => 'datetime'];
    }
}
