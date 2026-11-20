<?php

declare(strict_types=1);

namespace App\Modules\Erp\Valorizaciones\Models;

use Illuminate\Database\Eloquent\Model;

/** MVP rápido (tramo 4). */
class SubcontratoValorizacion extends Model
{
    protected $table = 'subcontrato_valorizaciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
