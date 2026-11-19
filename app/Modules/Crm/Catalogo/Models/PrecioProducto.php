<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

/** MVP rápido (t1-catalogo): un precio referencial con su vigencia; los cambios no sobrescriben. */
class PrecioProducto extends Model
{
    use ConAuditoria;

    protected $table = 'producto_precios';

    protected $fillable = ['producto_id', 'moneda_id', 'precio', 'costo_referencial', 'margen_objetivo', 'vigente_desde', 'vigente_hasta', 'es_vigente', 'origen'];

    protected function casts(): array
    {
        return ['precio' => 'decimal:4', 'costo_referencial' => 'decimal:4', 'margen_objetivo' => 'decimal:2', 'vigente_desde' => 'date', 'vigente_hasta' => 'date', 'es_vigente' => 'boolean'];
    }
}
