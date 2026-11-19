<?php

declare(strict_types=1);

namespace App\Modules\Erp\Contratos\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MVP rápido (t2-contratos): parte de un contrato. */
class HitoContrato extends Model
{
    protected $table = 'contrato_hitos';

    protected $fillable = ['contrato_id', 'nombre', 'fecha_prevista', 'fecha_real', 'orden'];

    protected function casts(): array
    {
        return ['fecha_prevista' => 'immutable_date', 'fecha_real' => 'immutable_date', 'orden' => 'integer'];
    }

    /** @return BelongsTo<Contrato, $this> */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
