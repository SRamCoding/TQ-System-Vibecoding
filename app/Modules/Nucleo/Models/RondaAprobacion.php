<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Enums\EstadoRondaAprobacion;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * M2 · Un pedido de aprobación (plan 1.7, D5): las firmas que tocaban al pedirla, atadas a la versión del documento.
 * Solo la escribe `AprobacionesService`.
 *
 * @property int $id
 * @property string $aprobable_type
 * @property int $aprobable_id
 * @property string $transicion_codigo
 * @property int $version_documento
 * @property int $solicitante_user_id
 * @property string $monto_soles
 * @property ?int $unidad_organizativa_id
 * @property EstadoRondaAprobacion $estado
 * @property ?Carbon $cerrada_at
 * @property Carbon $created_at
 */
class RondaAprobacion extends Model
{
    use ConAuditoria;

    protected $table = 'rondas_aprobacion';

    protected $fillable = ['aprobable_type', 'aprobable_id', 'transicion_codigo', 'version_documento', 'solicitante_user_id', 'monto_soles', 'unidad_organizativa_id', 'estado', 'cerrada_at'];

    protected function casts(): array
    {
        return ['estado' => EstadoRondaAprobacion::class, 'cerrada_at' => 'datetime', 'monto_soles' => 'decimal:2', 'version_documento' => 'integer'];
    }

    /**
     * @return HasMany<FirmaAprobacion, $this>
     */
    public function firmas(): HasMany
    {
        return $this->hasMany(FirmaAprobacion::class)->orderBy('orden');
    }
}
