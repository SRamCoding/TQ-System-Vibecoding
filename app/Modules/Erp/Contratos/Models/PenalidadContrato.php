<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MVP rápido (t2-contratos): parte de un contrato. */
class PenalidadContrato extends Model
{
    protected $table = 'contrato_penalidades';

    protected $fillable = ['contrato_id', 'fecha', 'dias_atraso', 'monto', 'motivo'];

    protected function casts(): array
    {
        return ['fecha' => 'immutable_date', 'monto' => 'decimal:2', 'dias_atraso' => 'integer'];
    }

    /** @return BelongsTo<Contrato, $this> */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
