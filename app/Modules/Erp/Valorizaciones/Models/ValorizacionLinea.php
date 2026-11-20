<?php

declare(strict_types=1);

namespace App\Modules\Erp\Valorizaciones\Models;

use Illuminate\Database\Eloquent\Model;

/** MVP rápido (tramo 4). */
class ValorizacionLinea extends Model
{
    protected $table = 'valorizacion_lineas';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [];
    }
}
