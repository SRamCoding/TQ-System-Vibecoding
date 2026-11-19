<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MVP rápido (t2-contratos): parte de un contrato. */
class GarantiaContrato extends Model
{
    protected $table = 'contrato_garantias';

    protected $fillable = ['contrato_id', 'tipo', 'banco', 'numero', 'monto', 'emision', 'vencimiento', 'comision', 'gasto_id', 'estado'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'comision' => 'decimal:2', 'emision' => 'immutable_date', 'vencimiento' => 'immutable_date'];
    }

    /** @return BelongsTo<Contrato, $this> */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
