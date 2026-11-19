<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\TipoTrabajoFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M5 · Tipo de trabajo de las horas de desarrollo y si por defecto se factura.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property bool $es_facturable_default
 * @property bool $es_activo
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(TipoTrabajoFactory::class)]
class TipoTrabajo extends Model implements ElementoCatalogo
{
    /** @use HasFactory<TipoTrabajoFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'tipos_trabajo';

    protected $fillable = ['codigo', 'nombre', 'es_facturable_default', 'es_activo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_facturable_default' => 'boolean',
            'es_activo' => 'boolean',
        ];
    }
}
