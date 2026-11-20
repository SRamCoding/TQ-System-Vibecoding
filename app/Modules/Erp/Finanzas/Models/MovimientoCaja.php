<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Models;

use Illuminate\Database\Eloquent\Model;

/** MVP rápido (t5-finanzas). */
class MovimientoCaja extends Model
{
    protected $table = 'movimientos_caja';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fecha' => 'immutable_date', 'conciliado' => 'boolean'];
    }
}
