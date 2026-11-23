<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MVP rápido (observación 7.3): lo que la entidad pide subsanar, con su plazo, y la respuesta con su constancia. */
class SubsanacionLicitacion extends Model
{
    protected $table = 'licitacion_subsanaciones';

    protected $fillable = ['licitacion_id', 'pedido', 'pedida_el', 'plazo', 'respuesta', 'constancia', 'respondida_el'];

    protected function casts(): array
    {
        return ['pedida_el' => 'date', 'plazo' => 'date', 'respondida_el' => 'date'];
    }

    /** @return BelongsTo<Licitacion, $this> */
    public function licitacion(): BelongsTo
    {
        return $this->belongsTo(Licitacion::class);
    }
}
