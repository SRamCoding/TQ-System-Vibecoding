<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** MVP rápido (t3-compras). */
class CotizacionProveedor extends Model
{
    protected $table = 'cuadro_cotizaciones';

    protected $fillable = ['cuadro_id', 'proveedor_party_id', 'plazo_dias', 'forma_pago', 'evaluacion'];

    protected function casts(): array
    {
        return ['evaluacion' => 'decimal:1'];
    }

    /** @return HasMany<PrecioCotizado, $this> */
    public function precios(): HasMany
    {
        return $this->hasMany(PrecioCotizado::class, 'cotizacion_id');
    }
}
