<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * M11 · El destino de una línea de costo y sus tres montos (plan 1.9; regla 3). Una por línea de origen. Solo la escribe
 * `ImputacionService`, con la fila bloqueada.
 *
 * @property int $id
 * @property string $origen_type
 * @property int $origen_id
 * @property ?int $centro_costo_id
 * @property ?int $partida_control_id
 * @property ?int $proyecto_id
 * @property bool $excede_partida
 * @property Carbon $fecha
 * @property int $moneda_id
 * @property string $tipo_cambio
 * @property string $monto_comprometido
 * @property string $monto_ejecutado
 * @property string $monto_pagado
 * @property ?int $motivo_id
 * @property ?string $comentario
 */
class Imputacion extends Model implements DeclaraCamposSensibles
{
    // Mueve dinero: cada cambio de destino o de monto queda en la bitácora.
    use ConAuditoria, RegistraCambios;

    protected $table = 'imputaciones';

    protected $fillable = [
        'origen_type', 'origen_id', 'centro_costo_id', 'partida_control_id', 'proyecto_id', 'fecha', 'moneda_id', 'tipo_cambio',
        'monto_comprometido', 'monto_ejecutado', 'monto_pagado', 'motivo_id', 'comentario', 'excede_partida',
    ];

    /**
     * Sin campos sensibles propios: los montos de una línea son los de su documento, que declara los suyos.
     *
     * @return array<string, GrupoSensible>
     */
    public function camposSensibles(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function camposNoSensibles(): array
    {
        return [
            'centro_costo_id' => 'A qué centro de costo va la línea: dice el destino, no un costo.',
            'partida_control_id' => 'A qué partida va la línea: dice el destino, no un costo.',
        ];
    }

    /**
     * Todo cuenta: el destino y los montos mueven dinero.
     *
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return [];
    }

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'tipo_cambio' => 'decimal:4',
            'monto_comprometido' => 'decimal:2',
            'monto_ejecutado' => 'decimal:2',
            'monto_pagado' => 'decimal:2',
            'excede_partida' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CentroCosto, $this>
     */
    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class);
    }
}
