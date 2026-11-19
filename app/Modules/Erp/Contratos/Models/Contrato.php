<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Party;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MVP rápido (t2-contratos): el contrato con el cliente.
 *
 * @property int $id
 * @property string $codigo
 * @property int $proyecto_id
 * @property string $monto
 * @property int $plazo_dias
 * @property string $estado
 * @property CarbonImmutable $fecha_inicio
 */
class Contrato extends Model
{
    use ConAuditoria;

    public const array ESTADOS = ['borrador' => 'Borrador', 'vigente' => 'Vigente', 'culminado' => 'Culminado', 'resuelto' => 'Resuelto', 'anulado' => 'Anulado'];

    public const array COLORES = ['borrador' => 'gray', 'vigente' => 'success', 'culminado' => 'info', 'resuelto' => 'danger', 'anulado' => 'gray'];

    protected $table = 'contratos';

    protected $fillable = ['codigo', 'numero_entidad', 'proyecto_id', 'oportunidad_id', 'cliente_party_id', 'objeto', 'es_publico', 'monto', 'igv_pct', 'fecha_firma',
        'fecha_inicio', 'plazo_dias', 'forma_pago', 'garantia_meses', 'penalidad_factor', 'penalidad_tope_pct', 'estado'];

    protected function casts(): array
    {
        return ['es_publico' => 'boolean', 'monto' => 'decimal:2', 'igv_pct' => 'decimal:2', 'fecha_firma' => 'immutable_date', 'fecha_inicio' => 'immutable_date',
            'plazo_dias' => 'integer', 'garantia_meses' => 'integer', 'penalidad_factor' => 'decimal:2', 'penalidad_tope_pct' => 'decimal:2', 'version' => 'integer'];
    }

    /** @return BelongsTo<Party, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'cliente_party_id');
    }

    /** @return HasMany<HitoContrato, $this> */
    public function hitos(): HasMany
    {
        return $this->hasMany(HitoContrato::class)->orderBy('orden');
    }

    /** @return HasMany<GarantiaContrato, $this> */
    public function garantias(): HasMany
    {
        return $this->hasMany(GarantiaContrato::class)->orderBy('vencimiento');
    }

    /** @return HasMany<AdendaContrato, $this> */
    public function adendas(): HasMany
    {
        return $this->hasMany(AdendaContrato::class)->orderBy('fecha');
    }

    /** @return HasMany<PenalidadContrato, $this> */
    public function penalidades(): HasMany
    {
        return $this->hasMany(PenalidadContrato::class)->orderBy('fecha');
    }
}
