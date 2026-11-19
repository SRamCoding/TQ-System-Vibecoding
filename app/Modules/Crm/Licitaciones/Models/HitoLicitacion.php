<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MVP rápido (t1-licitaciones): parte de una licitación. */
class HitoLicitacion extends Model
{
    protected $table = 'licitacion_hitos';

    protected $fillable = ['licitacion_id', 'etapa', 'fecha', 'orden', 'es_cumplido'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'es_cumplido' => 'boolean', 'orden' => 'integer'];
    }

    /** @return BelongsTo<Licitacion, $this> */
    public function licitacion(): BelongsTo
    {
        return $this->belongsTo(Licitacion::class);
    }
}
