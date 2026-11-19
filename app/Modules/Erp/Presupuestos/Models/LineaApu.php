<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Models;

use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\ParteDeDocumento;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un insumo en el análisis de precios unitarios de una partida (plan 2.9, D4): lo escrito, la cantidad efectiva por
 * unidad de partida (5 decimales, la que vale), el precio como foto y el parcial (efectiva × precio, 4 decimales).
 * La mano de obra y el equipo usan el rendimiento de la partida, salvo que lleven uno propio (D12); `rendimiento` y
 * su unidad guardan siempre el usado.
 *
 * @property int $id
 * @property int $presupuesto_partida_id
 * @property int $insumo_id
 * @property string $tipo_cantidad
 * @property ?string $cantidad_neta
 * @property string $desperdicio_pct
 * @property ?string $rendimiento
 * @property ?string $unidad_rendimiento
 * @property ?string $cuadrilla
 * @property ?string $horas_jornada
 * @property bool $rendimiento_propio
 * @property string $cantidad
 * @property string $precio
 * @property string $parcial
 * @property int $version
 * @property-read PartidaDePresupuesto $partida
 * @property-read Insumo $insumo
 */
class LineaApu extends Model implements DeclaraCamposSensibles, DescribibleEnBitacora
{
    use ConAuditoria, DescribeBitacora, ParteDeDocumento, RegistraCambios;

    public const string POR_CANTIDAD = 'cantidad';

    public const string POR_RENDIMIENTO = 'rendimiento';

    public const string POR_HORA = 'hora';

    public const string POR_JORNADA = 'jornada';

    protected $table = 'apu_insumos';

    protected $fillable = [
        'presupuesto_partida_id', 'insumo_id', 'tipo_cantidad', 'cantidad_neta', 'desperdicio_pct', 'rendimiento',
        'unidad_rendimiento', 'cuadrilla', 'horas_jornada', 'rendimiento_propio', 'cantidad', 'precio', 'parcial',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_neta' => 'decimal:5',
            'desperdicio_pct' => 'decimal:2',
            'rendimiento' => 'decimal:4',
            'cuadrilla' => 'decimal:2',
            'horas_jornada' => 'decimal:2',
            'rendimiento_propio' => 'boolean',
            'cantidad' => 'decimal:5',
            'precio' => 'decimal:4',
            'parcial' => 'decimal:4',
            'version' => 'integer',
        ];
    }

    public function documentoConEstados(): Model&DocumentoConEstados
    {
        return $this->partida->presupuesto;
    }

    public function camposSensibles(): array
    {
        return ['precio' => GrupoSensible::Costos, 'parcial' => GrupoSensible::Costos];
    }

    public function camposNoSensibles(): array
    {
        return [];
    }

    public function camposSinBitacora(): array
    {
        return [];
    }

    /** @return BelongsTo<PartidaDePresupuesto, $this> */
    public function partida(): BelongsTo
    {
        return $this->belongsTo(PartidaDePresupuesto::class, 'presupuesto_partida_id');
    }

    /** @return BelongsTo<Insumo, $this> */
    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }

    /** Plan 2.10: los nombres que muestra la pestaña «Historial» de la ficha del presupuesto. */
    public function etiquetasBitacora(): array
    {
        return [
            'insumo_id' => 'Insumo', 'tipo_cantidad' => 'Se calcula por', 'cantidad_neta' => 'Cantidad neta',
            'desperdicio_pct' => 'Desperdicio (%)', 'rendimiento' => 'Rendimiento', 'unidad_rendimiento' => 'Rendimiento por',
            'cuadrilla' => 'Cuadrilla', 'horas_jornada' => 'Horas de la jornada', 'rendimiento_propio' => 'Rendimiento propio',
            'cantidad' => 'Cantidad', 'precio' => 'Precio', 'parcial' => 'Parcial',
        ];
    }

    public function sujetoBitacora(): string
    {
        return 'APU de la partida '.(PartidaDePresupuesto::query()->whereKey($this->presupuesto_partida_id)->value('codigo') ?? '');
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return $campo === 'insumo_id' && $valor !== null
            ? (Insumo::query()->whereKey($valor)->value('codigo') ?? self::valorComun($valor))
            : self::valorComun($valor);
    }
}
