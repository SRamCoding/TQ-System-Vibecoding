<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo\Models;

use App\Modules\Crm\Catalogo\database\factories\ProductoFactory;
use App\Modules\Nucleo\Models\Concerns\CodigoInmutable;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Un producto o servicio del catálogo (plan 2.60, D1). Otros módulos lo leen por `CatalogoService` (C4), nunca por este
 * modelo. Su código no cambia nunca (`docs/convenciones/base-de-datos.md`, «Catálogos y datos maestros»): las líneas
 * de otros módulos lo guardan.
 *
 * @property int $id
 * @property string $codigo
 * @property string $descripcion
 * @property ?string $descripcion_corta
 * @property int $unidad_medida_id
 * @property bool $requiere_serie
 * @property bool $es_servicio
 * @property bool $es_activo
 * @property int $version
 * @property-read UnidadMedida $unidadMedida
 */
#[UseFactory(ProductoFactory::class)]
class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use CodigoInmutable, ConAuditoria, HasFactory;

    protected $table = 'productos';

    protected $fillable = ['codigo', 'descripcion', 'descripcion_corta', 'unidad_medida_id', 'requiere_serie', 'es_servicio', 'es_activo', 'familia_producto_id', 'linea_solucion_id', 'marca', 'modelo', 'cubso_id'];

    protected function casts(): array
    {
        return [
            'requiere_serie' => 'boolean',
            'es_servicio' => 'boolean',
            'es_activo' => 'boolean',
            'version' => 'integer',
        ];
    }

    /** D6: el código se guarda en mayúsculas y sin espacios en los extremos (la base lo exige igual). */
    public static function normalizarCodigo(string $codigo): string
    {
        return mb_strtoupper(trim($codigo));
    }

    public function columnasInmutables(): array
    {
        return ['codigo'];
    }

    /** @return Attribute<string, string> */
    protected function codigo(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => self::normalizarCodigo($value));
    }

    /** @return BelongsTo<UnidadMedida, $this> */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    /** @return BelongsTo<FamiliaProducto, $this> */
    public function familia(): BelongsTo
    {
        return $this->belongsTo(FamiliaProducto::class, 'familia_producto_id');
    }

    /** @return HasMany<PrecioProducto, $this> */
    public function precios(): HasMany
    {
        return $this->hasMany(PrecioProducto::class)->orderByDesc('vigente_desde')->orderByDesc('id');
    }

    /** @return HasOne<PrecioProducto, $this> */
    public function precioVigente(): HasOne
    {
        return $this->hasOne(PrecioProducto::class)->where('es_vigente', true);
    }
}
