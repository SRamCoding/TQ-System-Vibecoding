<?php

declare(strict_types=1);

namespace App\Modules\Erp\Personal\Models;

use Illuminate\Database\Eloquent\Model;

/** MVP rápido (tramo 4). */
class Asignacion extends Model
{
    protected $table = 'asignaciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['desde' => 'immutable_date', 'hasta' => 'immutable_date', 'porcentaje' => 'integer'];
    }
}
