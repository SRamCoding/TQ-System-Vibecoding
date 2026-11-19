<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * M12 · El precio por token de un modelo desde una fecha (plan 1.11, D3). Un precio nuevo es una fila nueva: cada
 * llamada guarda el que aplicó y el pasado no se recalcula, como la regla del IGV.
 *
 * @property int $id
 * @property int $modelo_id
 * @property Carbon $vigente_desde
 * @property string $usd_millon_entrada
 * @property string $usd_millon_salida
 */
class IaPrecioModelo extends Model
{
    use ConAuditoria, RegistraCambios;

    protected $table = 'ia_precios_modelo';

    protected $fillable = ['modelo_id', 'vigente_desde', 'usd_millon_entrada', 'usd_millon_salida'];

    protected function casts(): array
    {
        return ['vigente_desde' => 'date', 'usd_millon_entrada' => 'string', 'usd_millon_salida' => 'string'];
    }

    public function camposSinBitacora(): array
    {
        return [];
    }
}
