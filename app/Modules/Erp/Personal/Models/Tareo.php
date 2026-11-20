<?php

declare(strict_types=1);

namespace App\Modules\Erp\Personal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** MVP rápido (tramo 4). */
class Tareo extends Model
{
    protected $table = 'tareos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['semana' => 'immutable_date', 'cerrado_at' => 'immutable_datetime'];
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(TareoLinea::class);
    }
}
