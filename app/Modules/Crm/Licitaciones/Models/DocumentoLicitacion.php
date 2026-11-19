<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MVP rápido (t1-licitaciones): parte de una licitación. */
class DocumentoLicitacion extends Model
{
    protected $table = 'licitacion_documentos';

    protected $fillable = ['licitacion_id', 'nombre', 'origen', 'es_confirmado', 'es_listo', 'nota', 'vence', 'orden'];

    protected function casts(): array
    {
        return ['es_confirmado' => 'boolean', 'es_listo' => 'boolean', 'vence' => 'date', 'orden' => 'integer'];
    }

    /** @return BelongsTo<Licitacion, $this> */
    public function licitacion(): BelongsTo
    {
        return $this->belongsTo(Licitacion::class);
    }
}
