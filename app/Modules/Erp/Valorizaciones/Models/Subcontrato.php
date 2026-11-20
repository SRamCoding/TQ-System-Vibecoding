<?php

declare(strict_types=1);

namespace App\Modules\Erp\Valorizaciones\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** MVP rápido (tramo 4). */
class Subcontrato extends Model
{
    protected $table = 'subcontratos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }

    public function alcance(): HasMany
    {
        return $this->hasMany(SubcontratoAlcance::class);
    }

    public function valorizaciones(): HasMany
    {
        return $this->hasMany(SubcontratoValorizacion::class)->orderBy('numero');
    }
}
