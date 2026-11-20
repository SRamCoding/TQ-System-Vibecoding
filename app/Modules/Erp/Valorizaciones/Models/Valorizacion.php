<?php

declare(strict_types=1);

namespace App\Modules\Erp\Valorizaciones\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** MVP rápido (tramo 4). */
class Valorizacion extends Model
{
    protected $table = 'valorizaciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['aprobada_at' => 'immutable_datetime'];
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(ValorizacionLinea::class);
    }
}
