<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Models;

use Illuminate\Database\Eloquent\Model;

/** MVP rápido (t5-finanzas). */
class FondoGasto extends Model
{
    protected $table = 'fondo_gastos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fecha' => 'immutable_date', 'leido_por_ia' => 'boolean'];
    }
}
