<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MVP rápido (t2-contratos): parte de un contrato. */
class AdendaContrato extends Model
{
    protected $table = 'contrato_adendas';

    protected $fillable = ['contrato_id', 'numero', 'fecha', 'motivo', 'monto_adicional', 'dias_adicionales'];

    protected function casts(): array
    {
        return ['fecha' => 'immutable_date', 'monto_adicional' => 'decimal:2', 'dias_adicionales' => 'integer'];
    }

    /** @return BelongsTo<Contrato, $this> */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
