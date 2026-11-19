<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\CentroCostoFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M11 · Centro de costo (plan 1.9): el destino del gasto directo sin proyecto. Los iniciales son los del v4; los reales
 * los confirma Contabilidad (pendiente).
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property bool $es_activo
 * @property int $version
 */
#[UseFactory(CentroCostoFactory::class)]
class CentroCosto extends Model implements ElementoCatalogo
{
    /** @use HasFactory<CentroCostoFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'centros_costo';

    protected $fillable = ['codigo', 'nombre', 'es_activo'];

    protected function casts(): array
    {
        return ['es_activo' => 'boolean'];
    }
}
