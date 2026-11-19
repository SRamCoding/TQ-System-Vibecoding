<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Models;

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Enums\TipoRespaldo;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Nucleo\Adjuntos\ConAdjuntos;
use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\TieneEstados;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una versión de una cotización al cliente (plan 2.22, D2): `COT-2026-0184` v1, v2… comparten número. Se arma en
 * borrador; al emitirse se congela (TC, IGV, validez); solo una versión queda aceptada (D7). Los montos los calcula
 * `CalculoCotizacion`.
 *
 * @property int $id
 * @property string $numero
 * @property int $numero_version
 * @property int $oportunidad_id
 * @property int $cuenta_id
 * @property ?int $contacto_id
 * @property int $moneda_id
 * @property ?int $presupuesto_id
 * @property int $dias_validez
 * @property ?CarbonImmutable $fecha_emision
 * @property ?CarbonImmutable $fecha_vencimiento
 * @property ?CarbonImmutable $emitida_at
 * @property ?string $tipo_cambio
 * @property ?string $igv_porcentaje
 * @property ?int $plazo_entrega_dias
 * @property ?int $forma_pago_id
 * @property ?string $forma_pago_detalle
 * @property ?int $garantia_meses
 * @property ?string $lugar_entrega
 * @property ?int $motivo_version_id
 * @property ?string $motivo_version_detalle
 * @property string $subtotal
 * @property string $descuento_total
 * @property string $igv
 * @property string $total
 * @property string $costo_total
 * @property ?string $margen_porcentaje
 * @property ?string $margen_minimo
 * @property ?string $margen_objetivo
 * @property bool $es_aceptada
 * @property ?CarbonImmutable $fecha_aceptacion
 * @property ?TipoRespaldo $respaldo_tipo
 * @property ?string $respaldo_referencia
 * @property ?int $respaldo_adjunto_id
 * @property ?int $adjunto_pdf_id
 * @property int $estado_id
 * @property int $version
 * @property-read Estado $estado
 */
class Cotizacion extends Model implements ConAdjuntos, DeclaraCamposSensibles, DescribibleEnBitacora, DocumentoConEstados
{
    use ConAuditoria, DescribeBitacora, RegistraCambios, TieneEstados;

    protected $table = 'cotizaciones';

    protected $fillable = [
        'numero', 'numero_version', 'oportunidad_id', 'cuenta_id', 'contacto_id', 'moneda_id', 'presupuesto_id', 'dias_validez',
        'plazo_entrega_dias', 'forma_pago_id', 'forma_pago_detalle', 'garantia_meses', 'lugar_entrega', 'motivo_version_id',
        'motivo_version_detalle', 'subtotal', 'descuento_total', 'igv', 'total', 'costo_total', 'margen_porcentaje',
    ];

    protected function casts(): array
    {
        return [
            'numero_version' => 'integer',
            'dias_validez' => 'integer',
            'fecha_emision' => 'immutable_date',
            'fecha_vencimiento' => 'immutable_date',
            'emitida_at' => 'immutable_datetime',
            'tipo_cambio' => 'decimal:4',
            'igv_porcentaje' => 'decimal:2',
            'plazo_entrega_dias' => 'integer',
            'garantia_meses' => 'integer',
            'subtotal' => 'decimal:2',
            'descuento_total' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
            'costo_total' => 'decimal:2',
            'margen_porcentaje' => 'decimal:2',
            'margen_minimo' => 'decimal:2',
            'margen_objetivo' => 'decimal:2',
            'es_aceptada' => 'boolean',
            'fecha_aceptacion' => 'immutable_date',
            'respaldo_tipo' => TipoRespaldo::class,
            'version' => 'integer',
        ];
    }

    /**
     * Lo que solo ponen los efectos al emitir, aceptar o anular la aceptación, aunque la versión ya no sea editable (D9,
     * D10, D7). Los totales y el margen los recalcula el servicio en borrador.
     */
    public function camposDerivados(): array
    {
        return [
            'fecha_emision', 'fecha_vencimiento', 'emitida_at', 'tipo_cambio', 'igv_porcentaje',
            'es_aceptada', 'fecha_aceptacion', 'respaldo_tipo', 'respaldo_referencia', 'respaldo_adjunto_id', 'adjunto_pdf_id',
        ];
    }

    /** D15: el costo y el margen solo los ve quien tiene esos grupos. */
    public function camposSensibles(): array
    {
        return [
            'costo_total' => GrupoSensible::Costos,
            'margen_porcentaje' => GrupoSensible::Margen,
            'margen_minimo' => GrupoSensible::Margen,
            'margen_objetivo' => GrupoSensible::Margen,
        ];
    }

    public function camposNoSensibles(): array
    {
        return [
            'tipo_cambio' => 'Es el tipo de cambio del día, público.',
            'igv_porcentaje' => 'Es la tasa del impuesto, pública.',
            'cuenta_id' => 'Es la cuenta comercial del cliente (CRM), no una cuenta bancaria.',
        ];
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

    /** @return HasMany<LineaDeCotizacion, $this> */
    public function lineas(): HasMany
    {
        return $this->hasMany(LineaDeCotizacion::class, 'cotizacion_id')->orderBy('orden')->orderBy('id');
    }

    /** Historial de la ficha (D19). */
    public function etiquetasBitacora(): array
    {
        return [
            'contacto_id' => 'Atención', 'moneda_id' => 'Moneda', 'presupuesto_id' => 'Presupuesto', 'dias_validez' => 'Días de validez',
            'fecha_emision' => 'Emitida el', 'fecha_vencimiento' => 'Vence', 'tipo_cambio' => 'Tipo de cambio', 'igv_porcentaje' => 'IGV (%)',
            'plazo_entrega_dias' => 'Plazo de entrega (días)', 'forma_pago_id' => 'Forma de pago', 'forma_pago_detalle' => 'Detalle de la forma de pago',
            'garantia_meses' => 'Garantía (meses)', 'lugar_entrega' => 'Lugar de entrega', 'subtotal' => 'Subtotal', 'descuento_total' => 'Descuentos',
            'igv' => 'IGV', 'total' => 'Total', 'costo_total' => 'Costo', 'margen_porcentaje' => 'Margen (%)', 'margen_minimo' => 'Margen mínimo (%)',
            'margen_objetivo' => 'Margen objetivo (%)', 'es_aceptada' => 'Aceptada', 'fecha_aceptacion' => 'Aceptada el',
            'respaldo_tipo' => 'Respaldo', 'respaldo_referencia' => 'Referencia del respaldo', 'estado_id' => 'Estado',
        ];
    }

    public function sujetoBitacora(): string
    {
        return 'Encabezado';
    }

    /** Plan 2.22b: el respaldo de la aceptación (y otros archivos) cuelga de la versión. */
    public function puedeVerAdjuntos(User $usuario): bool
    {
        return $usuario->can(CotizacionesServiceProvider::PERMISO_VER);
    }

    public function puedeAdjuntar(User $usuario): bool
    {
        return app(CotizacionesService::class)->puedeSobre($usuario, $this->oportunidad_id);
    }
}
