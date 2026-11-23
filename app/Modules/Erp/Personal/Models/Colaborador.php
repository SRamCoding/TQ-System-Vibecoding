<?php

declare(strict_types=1);

namespace App\Modules\Erp\Personal\Models;

use Illuminate\Database\Eloquent\Model;

/** MVP rápido (tramo 4). */
class Colaborador extends Model
{
    protected $table = 'colaboradores';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['habilitado_hasta' => 'immutable_date', 'es_activo' => 'boolean', 'ingreso' => 'immutable_date', 'salida' => 'immutable_date', 'jornada' => 'array'];
    }
}
