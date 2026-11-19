<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Enums\EstadoFirmaAprobacion;
use App\Modules\Nucleo\Enums\FirmanteAprobacion;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * M2 · Una firma de una ronda (plan 1.7): qué jefatura debe firmar, en qué orden, y cómo terminó. Solo la escribe
 * `AprobacionesService`.
 *
 * @property int $id
 * @property int $ronda_aprobacion_id
 * @property int $regla_aprobacion_id
 * @property int $orden
 * @property FirmanteAprobacion $firmante
 * @property ?int $unidad_organizativa_id
 * @property EstadoFirmaAprobacion $estado
 * @property ?int $user_id
 * @property ?Carbon $firmado_at
 * @property bool $es_autoaprobacion
 * @property ?string $motivo_autoaprobacion
 * @property ?int $revisada_por_user_id
 * @property ?Carbon $revisada_at
 * @property ?int $motivo_id
 * @property ?string $comentario
 */
class FirmaAprobacion extends Model
{
    use ConAuditoria;

    protected $table = 'firmas_aprobacion';

    protected $fillable = [
        'ronda_aprobacion_id', 'regla_aprobacion_id', 'orden', 'firmante', 'unidad_organizativa_id', 'estado', 'user_id',
        'firmado_at', 'es_autoaprobacion', 'motivo_autoaprobacion', 'revisada_por_user_id', 'revisada_at', 'motivo_id', 'comentario',
    ];

    protected function casts(): array
    {
        return [
            'firmante' => FirmanteAprobacion::class,
            'estado' => EstadoFirmaAprobacion::class,
            'firmado_at' => 'datetime',
            'revisada_at' => 'datetime',
            'es_autoaprobacion' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RondaAprobacion, $this>
     */
    public function ronda(): BelongsTo
    {
        return $this->belongsTo(RondaAprobacion::class, 'ronda_aprobacion_id');
    }
}
