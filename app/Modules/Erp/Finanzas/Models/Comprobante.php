<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Models;

use Illuminate\Database\Eloquent\Model;

/** MVP rápido (t5-finanzas). */
class Comprobante extends Model
{
    protected $table = 'comprobantes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['emision' => 'immutable_date', 'vencimiento' => 'immutable_date', 'pago_programado' => 'immutable_date'];
    }
}
