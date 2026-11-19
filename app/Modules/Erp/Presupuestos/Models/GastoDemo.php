<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * MVP rápido (ficha t0-datos-demo, MV-003): el documento de origen provisional de un costo de demostración. Sus montos
 * comprometido, ejecutado y pagado están en su imputación. Se va con las órdenes de compra (tramo 3).
 *
 * @property int $id
 * @property string $numero
 * @property int $proyecto_id
 * @property int $partida_control_id
 * @property string $descripcion
 * @property string $proveedor
 * @property string $monto
 */
class GastoDemo extends Model
{
    protected $table = 'gastos_demo';

    protected $fillable = ['numero', 'proyecto_id', 'partida_control_id', 'descripcion', 'proveedor', 'fecha', 'monto', 'created_by'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'monto' => 'decimal:2'];
    }
}
