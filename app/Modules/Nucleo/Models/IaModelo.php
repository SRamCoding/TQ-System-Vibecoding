<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\IaModeloFactory;
use App\Modules\Nucleo\Excepciones\IaRechazada;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * M12 · Un modelo de un proveedor (plan 1.11, D5). Su `codigo` es el nombre con que el proveedor lo conoce: es dato,
 * no código. Uno solo puede ser el modelo por defecto del sistema (índice único parcial).
 *
 * @property int $id
 * @property int $proveedor_id
 * @property string $codigo
 * @property string $nombre
 * @property int $max_tokens_salida
 * @property bool $es_por_defecto
 * @property bool $es_activo
 * @property int $version
 * @property-read IaProveedor $proveedor
 */
#[UseFactory(IaModeloFactory::class)]
class IaModelo extends Model implements ElementoCatalogo
{
    /** @use HasFactory<IaModeloFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'ia_modelos';

    protected $fillable = ['proveedor_id', 'codigo', 'nombre', 'max_tokens_salida', 'es_por_defecto', 'es_activo'];

    /** Un modelo no cambia de proveedor: movería los datos de todos los procesos que lo usan sin que nadie lo note. */
    protected static function booted(): void
    {
        static::updating(function (self $modelo): void {
            if ($modelo->isDirty('proveedor_id')) {
                throw IaRechazada::modeloDeOtroProveedor();
            }
        });
    }

    protected function casts(): array
    {
        return ['es_por_defecto' => 'boolean', 'es_activo' => 'boolean', 'max_tokens_salida' => 'integer', 'version' => 'integer'];
    }

    /**
     * @return BelongsTo<IaProveedor, $this>
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(IaProveedor::class, 'proveedor_id');
    }

    /**
     * @return HasMany<IaPrecioModelo, $this>
     */
    public function precios(): HasMany
    {
        return $this->hasMany(IaPrecioModelo::class, 'modelo_id');
    }
}
