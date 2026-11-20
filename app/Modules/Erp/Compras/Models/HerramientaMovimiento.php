<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Models;

use Illuminate\Database\Eloquent\Model;

/** MV-028: cada salida, devolución o mantenimiento de una herramienta. */
class HerramientaMovimiento extends Model
{
    protected $fillable = ['herramienta_id', 'tipo', 'fecha', 'proyecto_id', 'colaborador_id', 'debe_volver', 'nota', 'user_id'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'debe_volver' => 'date'];
    }
}
