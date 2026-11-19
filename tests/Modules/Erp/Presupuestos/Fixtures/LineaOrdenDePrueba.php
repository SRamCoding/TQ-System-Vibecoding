<?php

declare(strict_types=1);

namespace Tests\Modules\Erp\Presupuestos\Fixtures;

use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\ParteDeDocumento;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea con costo de la orden de prueba: su destino vive en `imputaciones` (plan 2.2, D2).
 *
 * @property int $id
 * @property int $orden_prueba_id
 * @property string $descripcion
 * @property string $monto
 * @property string $moneda
 * @property-read OrdenDePrueba $orden
 */
class LineaOrdenDePrueba extends Model
{
    use ConAuditoria, ParteDeDocumento;

    protected $table = 'lineas_orden_prueba';

    protected $fillable = ['orden_prueba_id', 'descripcion', 'monto', 'moneda', 'fecha'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'fecha' => 'immutable_date'];
    }

    public function documentoConEstados(): Model&DocumentoConEstados
    {
        return $this->orden;
    }

    /** @return BelongsTo<OrdenDePrueba, $this> */
    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenDePrueba::class, 'orden_prueba_id');
    }
}
