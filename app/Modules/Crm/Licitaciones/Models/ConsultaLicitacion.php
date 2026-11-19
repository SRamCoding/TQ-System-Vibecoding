<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MVP rápido (t1-licitaciones): parte de una licitación. */
class ConsultaLicitacion extends Model
{
    protected $table = 'licitacion_consultas';

    protected $fillable = ['licitacion_id', 'pregunta', 'respuesta', 'fecha'];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    /** @return BelongsTo<Licitacion, $this> */
    public function licitacion(): BelongsTo
    {
        return $this->belongsTo(Licitacion::class);
    }
}
