<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MVP rápido (t1-licitaciones): parte de una licitación. */
class ConsultaLicitacion extends Model
{
    protected $table = 'licitacion_consultas';

    /** Observación 7.3: borrador (redactada, aún no enviada), enviada (con su constancia) y absuelta. */
    public const array ESTADOS = ['borrador' => 'Borrador', 'enviada' => 'Enviada', 'absuelta' => 'Absuelta'];

    protected $fillable = ['licitacion_id', 'pregunta', 'respuesta', 'fecha', 'tipo', 'numeral', 'estado', 'origen', 'enviada_el', 'constancia'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'enviada_el' => 'date'];
    }

    /** @return BelongsTo<Licitacion, $this> */
    public function licitacion(): BelongsTo
    {
        return $this->belongsTo(Licitacion::class);
    }
}
