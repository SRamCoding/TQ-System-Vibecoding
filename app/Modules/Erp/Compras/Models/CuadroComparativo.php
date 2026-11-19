<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** MVP rápido (t3-compras). */
class CuadroComparativo extends Model
{
    use ConAuditoria;

    protected $table = 'cuadros_comparativos';

    protected $fillable = ['codigo', 'requerimiento_id', 'estado', 'cotizacion_elegida_id', 'motivo'];

    protected function casts(): array
    {
        return [];
    }

    /** @return HasMany<CotizacionProveedor, $this> */
    public function cotizaciones(): HasMany
    {
        return $this->hasMany(CotizacionProveedor::class, 'cuadro_id');
    }
}
