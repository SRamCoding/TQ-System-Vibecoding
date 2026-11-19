<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Enums\FirmanteAprobacion;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M2 · Un paso de una regla: qué jefatura firma, en su orden (plan 1.7, D1 y D2).
 *
 * @property int $id
 * @property int $regla_aprobacion_id
 * @property int $orden
 * @property FirmanteAprobacion $firmante
 * @property ?int $unidad_organizativa_id
 */
class PasoReglaAprobacion extends Model
{
    use ConAuditoria;

    protected $table = 'pasos_regla_aprobacion';

    protected $fillable = ['regla_aprobacion_id', 'orden', 'firmante', 'unidad_organizativa_id'];

    protected function casts(): array
    {
        return ['firmante' => FirmanteAprobacion::class, 'orden' => 'integer'];
    }

    /**
     * @return BelongsTo<UnidadOrganizativa, $this>
     */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }
}
