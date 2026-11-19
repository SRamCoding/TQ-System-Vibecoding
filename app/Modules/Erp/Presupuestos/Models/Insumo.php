<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Todo lo que cuesta en un APU (plan 2.9, D8): material, mano de obra, equipo o subcontrato. Un material puede ir
 * enlazado a un producto de C4 (uno a uno, con su misma unidad); la mano de obra, a una categoría de C5 cuando exista.
 * El del sistema es «Precio sin detallar» (D10): no se edita ni se desactiva.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $tipo
 * @property int $unidad_medida_id
 * @property ?string $precio_referencia
 * @property ?int $producto_id
 * @property ?int $categoria_costo_hora_id
 * @property bool $es_sistema
 * @property bool $es_activo
 * @property int $version
 * @property-read UnidadMedida $unidadMedida
 */
class Insumo extends Model implements DeclaraCamposSensibles
{
    use ConAuditoria, RegistraCambios;

    public const string MATERIAL = 'material';

    public const string MANO_OBRA = 'mano_obra';

    public const string EQUIPO = 'equipo';

    public const string SUBCONTRATO = 'subcontrato';

    public const array TIPOS = [self::MATERIAL, self::MANO_OBRA, self::EQUIPO, self::SUBCONTRATO];

    public const string CODIGO_SIN_DETALLAR = 'SIN-DETALLE';

    protected $table = 'insumos';

    protected $fillable = ['codigo', 'nombre', 'tipo', 'unidad_medida_id', 'precio_referencia', 'producto_id', 'categoria_costo_hora_id', 'es_activo'];

    protected function casts(): array
    {
        return ['precio_referencia' => 'decimal:4', 'es_sistema' => 'boolean', 'es_activo' => 'boolean', 'version' => 'integer'];
    }

    /** Mano de obra y equipo se escriben por rendimiento; material y subcontrato, por cantidad (D4). */
    public function vaPorRendimiento(): bool
    {
        return in_array($this->tipo, [self::MANO_OBRA, self::EQUIPO], true);
    }

    public function camposSensibles(): array
    {
        return ['precio_referencia' => GrupoSensible::Costos];
    }

    public function camposNoSensibles(): array
    {
        return ['categoria_costo_hora_id' => 'Es la categoría del trabajador (C5), no su costo.'];
    }

    public function camposSinBitacora(): array
    {
        return [];
    }

    /** @return BelongsTo<UnidadMedida, $this> */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }
}
