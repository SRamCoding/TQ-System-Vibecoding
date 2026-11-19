<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Models;

use Illuminate\Database\Eloquent\Model;

/** MVP rápido (t3-compras). */
class PrecioCotizado extends Model
{
    protected $table = 'cuadro_precios';

    protected $fillable = ['cotizacion_id', 'requerimiento_linea_id', 'precio_unitario'];

    protected function casts(): array
    {
        return ['precio_unitario' => 'decimal:4'];
    }

    public $timestamps = false;
}
