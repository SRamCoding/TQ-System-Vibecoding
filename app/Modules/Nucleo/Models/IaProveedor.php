<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\IaProveedorFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * M12 · Un proveedor de modelos de lenguaje (plan 1.11, D5): la Claude API, un modelo local u otra cuenta de la
 * empresa. Su clave nunca se guarda: `variable_clave` es el nombre de la variable de entorno que la tiene, siempre
 * `IA_CLAVE_…` (CHECK en la base), para que no pueda apuntar a otro secreto.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $implementacion
 * @property bool $es_externo
 * @property ?string $variable_clave
 * @property int $llamadas_en_paralelo
 * @property bool $es_activo
 * @property int $version
 */
#[UseFactory(IaProveedorFactory::class)]
class IaProveedor extends Model implements ElementoCatalogo
{
    /** @use HasFactory<IaProveedorFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    public const string PATRON_VARIABLE_CLAVE = '/^IA_CLAVE_[A-Z0-9_]+$/';

    protected $table = 'ia_proveedores';

    protected $fillable = ['codigo', 'nombre', 'implementacion', 'es_externo', 'variable_clave', 'llamadas_en_paralelo', 'es_activo'];

    protected function casts(): array
    {
        return ['es_externo' => 'boolean', 'es_activo' => 'boolean', 'llamadas_en_paralelo' => 'integer', 'version' => 'integer'];
    }

    /**
     * @return HasMany<IaModelo, $this>
     */
    public function modelos(): HasMany
    {
        return $this->hasMany(IaModelo::class, 'proveedor_id');
    }
}
