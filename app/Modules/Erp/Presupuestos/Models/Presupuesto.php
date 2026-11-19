<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Models;

use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\TieneEstados;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una versión de un presupuesto (plan 2.9). Se arma en borrador; al ofertarse se congela; al traerse al proyecto queda
 * aprobado y vigente; una versión nueva lo reemplaza (D6). Los montos los calcula `CalculoPresupuesto` (D5).
 *
 * @property int $id
 * @property string $numero
 * @property int $numero_version
 * @property ?int $oportunidad_id
 * @property ?int $proyecto_id
 * @property string $nombre
 * @property int $moneda_id
 * @property bool $usa_apu
 * @property int $decimales_precio_unitario
 * @property string $gastos_generales_pct
 * @property string $utilidad_pct
 * @property string $igv_pct
 * @property string $costo_directo
 * @property string $gastos_generales
 * @property string $utilidad
 * @property string $igv
 * @property string $total
 * @property bool $es_vigente
 * @property int $estado_id
 * @property int $version
 * @property-read Estado $estado
 */
class Presupuesto extends Model implements DeclaraCamposSensibles, DescribibleEnBitacora, DocumentoConEstados
{
    use ConAuditoria, DescribeBitacora, RegistraCambios, TieneEstados;

    protected $table = 'presupuestos';

    protected $fillable = [
        'numero', 'numero_version', 'oportunidad_id', 'proyecto_id', 'origen_cotizacion_id', 'nombre', 'moneda_id', 'usa_apu', 'decimales_precio_unitario',
        'gastos_generales_pct', 'utilidad_pct', 'igv_pct', 'costo_directo', 'gastos_generales', 'utilidad', 'igv', 'total',
    ];

    protected function casts(): array
    {
        return [
            'numero_version' => 'integer',
            'usa_apu' => 'boolean',
            'decimales_precio_unitario' => 'integer',
            'es_vigente' => 'boolean',
            'gastos_generales_pct' => 'decimal:2',
            'utilidad_pct' => 'decimal:2',
            'igv_pct' => 'decimal:2',
            'costo_directo' => 'decimal:2',
            'gastos_generales' => 'decimal:2',
            'utilidad' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
            'version' => 'integer',
        ];
    }

    /** Los pone el efecto de «Traer al proyecto» y el de «Reemplazar» (D6), aunque el presupuesto ya no sea editable. */
    public function camposDerivados(): array
    {
        return ['proyecto_id', 'es_vigente'];
    }

    public function camposSensibles(): array
    {
        return [
            'costo_directo' => GrupoSensible::Costos,
            'gastos_generales' => GrupoSensible::Costos,
            'utilidad' => GrupoSensible::Margen,
            'utilidad_pct' => GrupoSensible::Margen,
        ];
    }

    public function camposNoSensibles(): array
    {
        return ['decimales_precio_unitario' => 'Es cuántos decimales lleva el P.U., no un precio.'];
    }

    public function camposSinBitacora(): array
    {
        return [];
    }

    /** @return BelongsTo<Estado, $this> */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    /** @return HasMany<TituloDePresupuesto, $this> */
    public function titulos(): HasMany
    {
        return $this->hasMany(TituloDePresupuesto::class, 'presupuesto_id');
    }

    /** @return HasMany<PartidaDePresupuesto, $this> */
    public function partidas(): HasMany
    {
        return $this->hasMany(PartidaDePresupuesto::class, 'presupuesto_id');
    }

    /** Plan 2.10: los nombres que muestra la pestaña «Historial» de la ficha del presupuesto. */
    public function etiquetasBitacora(): array
    {
        return [
            'numero' => 'Número', 'numero_version' => 'Versión', 'nombre' => 'Nombre', 'oportunidad_id' => 'Oportunidad',
            'proyecto_id' => 'Proyecto', 'moneda_id' => 'Moneda', 'usa_apu' => 'Con análisis de precios',
            'decimales_precio_unitario' => 'Decimales del P.U.', 'gastos_generales_pct' => 'Gastos generales (%)',
            'utilidad_pct' => 'Utilidad (%)', 'igv_pct' => 'IGV (%)', 'costo_directo' => 'Costo directo',
            'gastos_generales' => 'Gastos generales', 'utilidad' => 'Utilidad', 'igv' => 'IGV', 'total' => 'Total',
            'es_vigente' => 'Vigente', 'estado_id' => 'Estado',
        ];
    }

    public function sujetoBitacora(): string
    {
        return 'Encabezado';
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return match (true) {
            $valor === null => '—',
            $campo === 'moneda_id' => Moneda::query()->whereKey($valor)->value('codigo') ?? self::valorComun($valor),
            $campo === 'estado_id' => Estado::query()->whereKey($valor)->value('nombre') ?? self::valorComun($valor),
            default => self::valorComun($valor),
        };
    }
}
