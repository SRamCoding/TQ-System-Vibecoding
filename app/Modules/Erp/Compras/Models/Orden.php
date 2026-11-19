<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** MVP rápido (t3-compras). */
class Orden extends Model
{
    use ConAuditoria;

    protected $table = 'ordenes';

    protected $fillable = ['codigo', 'clase', 'tipo_compra_id', 'proveedor_party_id', 'proveedor_nombre', 'requerimiento_id', 'cuadro_id', 'proyecto_id', 'estado', 'fecha', 'entrega_pactada', 'lugar_entrega', 'forma_pago', 'motivo_exceso', 'seguimiento', 'aprobado_por', 'aprobado_at'];

    protected function casts(): array
    {
        return ['fecha' => 'immutable_date', 'entrega_pactada' => 'immutable_date', 'aprobado_at' => 'immutable_datetime'];
    }

    /** @return HasMany<OrdenLinea, $this> */
    public function lineas(): HasMany
    {
        return $this->hasMany(OrdenLinea::class);
    }

    /** @return HasMany<Recepcion, $this> */
    public function recepciones(): HasMany
    {
        return $this->hasMany(Recepcion::class);
    }

    /** @return BelongsTo<TipoCompra, $this> */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoCompra::class, 'tipo_compra_id');
    }
}
