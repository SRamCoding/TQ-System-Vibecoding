<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Models;

use Illuminate\Database\Eloquent\Model;

/** MVP rápido (t3-compras). */
class Almacen extends Model
{
    protected $table = 'almacenes';

    protected $fillable = ['codigo', 'nombre', 'tipo', 'proyecto_id'];

    protected function casts(): array
    {
        return [];
    }
}
