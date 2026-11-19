<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\CubsoFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M5 · Código del Catálogo Único de Bienes, Servicios y Obras del Estado. Sin carga hasta que Licitaciones lo pida.
 *
 * @property int $id
 * @property string $codigo
 * @property string $descripcion
 * @property ?int $unidad_medida_id
 * @property ?string $version_catalogo
 * @property bool $es_activo
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(CubsoFactory::class)]
class Cubso extends Model implements ElementoCatalogo
{
    /** @use HasFactory<CubsoFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'cubso';

    protected $fillable = ['codigo', 'descripcion', 'unidad_medida_id', 'version_catalogo', 'es_activo'];

    public function etiqueta(): string
    {
        return "{$this->codigo} · {$this->descripcion}";
    }

    /**
     * @return BelongsTo<UnidadMedida, $this>
     */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unidad_medida_id' => 'integer',
            'es_activo' => 'boolean',
        ];
    }
}
