<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Models;

use App\Modules\Crm\Cotizaciones\Enums\OrigenLinea;
use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\ParteDeDocumento;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea de una versión de la cotización (plan 2.22, D6): del catálogo, libre o el presupuesto enlazado. Guarda su
 * texto y su unidad (foto: el catálogo no la reescribe).
 *
 * @property int $id
 * @property int $cotizacion_id
 * @property OrigenLinea $origen
 * @property ?int $producto_id
 * @property string $descripcion
 * @property int $unidad_medida_id
 * @property string $cantidad
 * @property string $precio_unitario
 * @property string $descuento_porcentaje
 * @property string $costo_unitario
 * @property string $subtotal
 * @property ?string $margen_porcentaje
 * @property int $orden
 * @property int $version
 */
class LineaDeCotizacion extends Model implements DeclaraCamposSensibles, DescribibleEnBitacora
{
    use ConAuditoria, DescribeBitacora, ParteDeDocumento, RegistraCambios;

    protected $table = 'cotizacion_lineas';

    protected $fillable = [
        'cotizacion_id', 'origen', 'producto_id', 'descripcion', 'unidad_medida_id', 'cantidad', 'precio_unitario',
        'descuento_porcentaje', 'costo_unitario', 'subtotal', 'margen_porcentaje', 'orden',
    ];

    protected function casts(): array
    {
        return [
            'origen' => OrigenLinea::class,
            'cantidad' => 'decimal:3',
            'precio_unitario' => 'decimal:4',
            'descuento_porcentaje' => 'decimal:2',
            'costo_unitario' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'margen_porcentaje' => 'decimal:2',
            'orden' => 'integer',
            'version' => 'integer',
        ];
    }

    public function camposSensibles(): array
    {
        return [
            'costo_unitario' => GrupoSensible::Costos,
            'margen_porcentaje' => GrupoSensible::Margen,
        ];
    }

    public function camposNoSensibles(): array
    {
        return ['precio_unitario' => 'Es el precio de venta que se cotiza al cliente; el costo y el margen sí son sensibles.'];
    }

    public function camposSinBitacora(): array
    {
        return [];
    }

    /** Sigue el estado de su versión: en una no editable no se agrega, no se cambia ni se borra (regla 6). */
    public function documentoConEstados(): Model&DocumentoConEstados
    {
        return $this->cotizacion()->firstOrFail();
    }

    /** @return BelongsTo<Cotizacion, $this> */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    /** Historial de la ficha (D19). */
    public function etiquetasBitacora(): array
    {
        return [
            'origen' => 'Origen', 'producto_id' => 'Producto', 'descripcion' => 'Descripción', 'unidad_medida_id' => 'Unidad',
            'cantidad' => 'Cantidad', 'precio_unitario' => 'P.U. sin IGV', 'descuento_porcentaje' => 'Descuento (%)',
            'costo_unitario' => 'Costo unitario', 'subtotal' => 'Subtotal', 'margen_porcentaje' => 'Margen (%)', 'orden' => 'Orden',
        ];
    }

    public function sujetoBitacora(): string
    {
        return "Línea {$this->orden} · {$this->descripcion}";
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return $campo === 'unidad_medida_id' && $valor !== null
            ? (UnidadMedida::query()->whereKey($valor)->value('codigo') ?? self::valorComun($valor))
            : self::valorComun($valor);
    }
}
