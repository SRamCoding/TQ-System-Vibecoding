<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MVP rápido (t3-compras). */
class OrdenLinea extends Model
{
    protected $table = 'orden_lineas';

    protected $fillable = ['orden_id', 'requerimiento_linea_id', 'producto_id', 'descripcion', 'unidad_medida_id', 'cantidad', 'precio_unitario', 'subtotal', 'partida_control_id', 'centro_costo_id', 'motivo_id', 'comentario', 'cantidad_recibida'];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:3', 'precio_unitario' => 'decimal:4', 'subtotal' => 'decimal:2', 'cantidad_recibida' => 'decimal:3'];
    }

    /** @return BelongsTo<Orden, $this> */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(Orden::class);
    }
}
