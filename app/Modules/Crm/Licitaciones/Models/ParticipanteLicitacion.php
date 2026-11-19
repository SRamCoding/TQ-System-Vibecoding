<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MVP rápido (t1-licitaciones): parte de una licitación. */
class ParticipanteLicitacion extends Model
{
    protected $table = 'licitacion_participantes';

    protected $fillable = ['licitacion_id', 'nombre', 'party_id', 'es_techquk', 'oferta', 'puntaje', 'puesto', 'es_ganador', 'observacion'];

    protected function casts(): array
    {
        return ['es_techquk' => 'boolean', 'es_ganador' => 'boolean', 'oferta' => 'decimal:2', 'puntaje' => 'decimal:2', 'puesto' => 'integer'];
    }

    /** @return BelongsTo<Licitacion, $this> */
    public function licitacion(): BelongsTo
    {
        return $this->belongsTo(Licitacion::class);
    }
}
