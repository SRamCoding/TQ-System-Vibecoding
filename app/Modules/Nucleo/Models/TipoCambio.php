<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\TipoCambioFactory;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M5 · Tipo de cambio de una moneda en un día: compra y venta por separado, porque SUNAT exige venta
 * para compras y compra para ventas. Sensible: corregirlo cambia los montos convertidos (plan 1.2, D3).
 *
 * @property int $id
 * @property int $moneda_id
 * @property CarbonImmutable $fecha
 * @property string $compra
 * @property string $venta
 * @property OrigenTipoCambio $origen
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(TipoCambioFactory::class)]
class TipoCambio extends Model
{
    /** @use HasFactory<TipoCambioFactory> */
    use ConAuditoria, HasFactory, RegistraCambios;

    protected $table = 'tipos_cambio';

    protected $fillable = ['moneda_id', 'fecha', 'compra', 'venta', 'origen'];

    /**
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return [];
    }

    /**
     * @return BelongsTo<Moneda, $this>
     */
    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'moneda_id' => 'integer',
            'fecha' => 'immutable_date',
            'compra' => 'decimal:4',
            'venta' => 'decimal:4',
            'origen' => OrigenTipoCambio::class,
        ];
    }
}
