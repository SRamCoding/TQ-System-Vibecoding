<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MVP rápido (t3-compras). */
class RequerimientoLinea extends Model
{
    protected $table = 'requerimiento_lineas';

    protected $fillable = ['requerimiento_id', 'tipo_compra_id', 'producto_id', 'descripcion', 'unidad_medida_id', 'cantidad', 'precio_estimado', 'partida_control_id', 'motivo_id', 'atencion', 'estado', 'orden'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:3', 'precio_estimado' => 'decimal:4'];
    }

    /** @return BelongsTo<Requerimiento, $this> */
    public function requerimiento(): BelongsTo
    {
        return $this->belongsTo(Requerimiento::class);
    }

    /** @return BelongsTo<TipoCompra, $this> */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoCompra::class, 'tipo_compra_id');
    }
}
