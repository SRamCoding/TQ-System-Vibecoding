<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Models;

use Illuminate\Database\Eloquent\Model;

/** MVP rápido (t3-compras). */
class TipoCompra extends Model
{
    protected $table = 'tipos_compra';

    protected $fillable = ['codigo', 'nombre', 'ejemplos', 'atencion', 'es_bien', 'comportamiento', 'es_activo', 'orden'];

    protected function casts(): array
    {
        return ['es_bien' => 'boolean', 'es_activo' => 'boolean'];
    }

    public $timestamps = false;
}
