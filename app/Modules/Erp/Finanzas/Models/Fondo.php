<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Models;

use Illuminate\Database\Eloquent\Model;

/** MVP rápido (t5-finanzas). */
class Fondo extends Model
{
    protected $table = 'fondos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['entregado' => 'immutable_date'];
    }
}
