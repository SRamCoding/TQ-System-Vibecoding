<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Models;

use App\Modules\Crm\Oportunidades\database\factories\OportunidadFactory;
use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\Enums\GanadorConocido;
use App\Modules\Crm\Oportunidades\Estados\SobreLoPropio;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Nucleo\Adjuntos\ConAdjuntos;
use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\TieneEstados;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\BuscaSinAcentos;
use App\Modules\Nucleo\Models\Concerns\CodigoInmutable;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\LineaSolucion;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\OrigenComercial;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use App\Modules\Nucleo\Services\SeguridadService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El negocio en curso (plan 2.21, D1): la etapa es su estado. Solo la escribe OportunidadesService. La cuenta es un
 * `cuenta_id`: se lee con `CuentasService`, nunca con el modelo de Cuentas (regla 2). Las relaciones con modelos del
 * núcleo son solo para leer.
 *
 * @property int $id
 * @property string $numero
 * @property int $cuenta_id
 * @property string $nombre
 * @property ?string $descripcion
 * @property string $monto_estimado
 * @property int $moneda_id
 * @property CarbonImmutable $fecha_cierre_estimada
 * @property ?CarbonImmutable $fecha_cierre_real
 * @property int $estado_id
 * @property CarbonImmutable $en_etapa_desde
 * @property int $origen_comercial_id
 * @property ?string $origen_detalle
 * @property int $ejecutivo_user_id
 * @property int $linea_solucion_id
 * @property bool $necesidad_confirmada
 * @property bool $presupuesto_confirmado
 * @property ?string $necesidad_detalle
 * @property ?int $decisor_contacto_id
 * @property ?GanadorConocido $ganador_conocido
 * @property int $version
 * @property ?string $foto_ruta
 * @property CarbonImmutable $created_at
 * @property-read Estado $estado
 * @property-read User $ejecutivo
 * @property-read Moneda $moneda
 */
#[UseFactory(OportunidadFactory::class)]
class Oportunidad extends Model implements ConAdjuntos, DeclaraCamposSensibles, DescribibleEnBitacora, DocumentoConEstados
{
    /** @use HasFactory<OportunidadFactory> */
    use BuscaSinAcentos, CodigoInmutable, ConAuditoria, DescribeBitacora, HasFactory, RegistraCambios, TieneEstados;

    protected $table = 'oportunidades';

    protected $fillable = [
        'cuenta_id', 'nombre', 'descripcion', 'monto_estimado', 'moneda_id', 'fecha_cierre_estimada', 'fecha_cierre_real',
        'en_etapa_desde', 'origen_comercial_id', 'origen_detalle', 'ejecutivo_user_id', 'linea_solucion_id', 'necesidad_confirmada',
        'presupuesto_confirmado', 'decisor_contacto_id', 'ganador_conocido',
    ];

    protected function casts(): array
    {
        return [
            'cuenta_id' => 'integer',
            'monto_estimado' => 'decimal:2',
            'moneda_id' => 'integer',
            'fecha_cierre_estimada' => 'immutable_date',
            'fecha_cierre_real' => 'immutable_date',
            'estado_id' => 'integer',
            'en_etapa_desde' => 'immutable_date',
            'origen_comercial_id' => 'integer',
            'ejecutivo_user_id' => 'integer',
            'linea_solucion_id' => 'integer',
            'necesidad_confirmada' => 'boolean',
            'presupuesto_confirmado' => 'boolean',
            'decisor_contacto_id' => 'integer',
            'ganador_conocido' => GanadorConocido::class,
            'version' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** La cuenta no cambia después de crear (D1); el número, nunca. */
    public function columnasInmutables(): array
    {
        return ['numero', 'cuenta_id'];
    }

    /**
     * `en_etapa_desde` la mantiene el efecto EntraEnEtapa, también al entrar en un estado final (ganada, perdida,
     * anulada): sin declararla, ese guardado se rechaza si la entrada cae otro día que la anterior.
     */
    public function camposDerivados(): array
    {
        return ['en_etapa_desde'];
    }

    /** Plan 2.21d, D8: sus archivos (la certificación presupuestal de la compuerta) los ve quien ve oportunidades. */
    public function puedeVerAdjuntos(User $usuario): bool
    {
        return $usuario->can(OportunidadesServiceProvider::PERMISO_VER);
    }

    /** Adjunta quien puede editarla: su ejecutivo con «editar las propias», o quien edita todas (Gerencia Comercial). */
    public function puedeAdjuntar(User $usuario): bool
    {
        return app(SeguridadService::class)->puedeSobrePropio($usuario, SobreLoPropio::EDITAR, SobreLoPropio::EDITAR_PROPIAS, $this->ejecutivo_user_id);
    }

    public function camposSinBitacora(): array
    {
        // La foto (plan 2.21d): su cambio lo anota la acción registrada; la ruta no le dice nada a nadie.
        return ['version', 'foto_ruta'];
    }

    public function camposSensibles(): array
    {
        return [];
    }

    public function camposNoSensibles(): array
    {
        return ['cuenta_id' => 'La cuenta comercial (CRM) de la oportunidad: no es una cuenta bancaria.'];
    }

    public function etiquetasBitacora(): array
    {
        return [
            'numero' => 'Número', 'cuenta_id' => 'Cuenta', 'nombre' => 'Nombre', 'descripcion' => 'Descripción',
            'monto_estimado' => 'Monto estimado', 'moneda_id' => 'Moneda', 'fecha_cierre_estimada' => 'Cierre estimado',
            'fecha_cierre_real' => 'Cierre real', 'estado_id' => 'Etapa', 'en_etapa_desde' => 'En la etapa desde',
            'origen_comercial_id' => 'Origen', 'origen_detalle' => 'Detalle del origen', 'ejecutivo_user_id' => 'Ejecutivo', 'linea_solucion_id' => 'Línea de solución',
            'necesidad_confirmada' => 'Necesidad confirmada', 'presupuesto_confirmado' => 'Presupuesto confirmado',
            'decisor_contacto_id' => 'Decisor', 'necesidad_detalle' => 'Cómo se confirmó la necesidad', 'ganador_conocido' => '¿Lo ganó otra empresa?',
        ];
    }

    public function sujetoBitacora(): string
    {
        return 'Oportunidad';
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return match (true) {
            $valor === null => '—',
            $campo === 'ejecutivo_user_id' => User::query()->whereKey($valor)->value('name') ?? self::valorComun($valor),
            $campo === 'origen_comercial_id' => OrigenComercial::query()->whereKey($valor)->value('nombre') ?? self::valorComun($valor),
            $campo === 'linea_solucion_id' => LineaSolucion::query()->whereKey($valor)->value('nombre') ?? self::valorComun($valor),
            $campo === 'moneda_id' => Moneda::query()->whereKey($valor)->value('codigo') ?? self::valorComun($valor),
            $campo === 'estado_id' => Estado::query()->whereKey($valor)->value('nombre') ?? self::valorComun($valor),
            $campo === 'ganador_conocido' => GanadorConocido::tryFrom((string) $valor)?->etiqueta() ?? self::valorComun($valor),
            in_array($campo, ['fecha_cierre_estimada', 'fecha_cierre_real', 'en_etapa_desde'], true) => CarbonImmutable::parse((string) $valor)->format('d/m/Y'),
            default => self::valorComun($valor),
        };
    }

    /**
     * Las que siguen en el embudo (D1): en una etapa abierta. En la base, sin traer filas a PHP.
     *
     * @param  Builder<self>  $consulta
     */
    public function scopeAbiertas(Builder $consulta): void
    {
        $consulta->whereIn('oportunidades.estado_id', Estado::query()->select('id')->where('documento_type', (new self)->getMorphClass())
            ->whereIn('codigo', array_map(fn (EtapaAbierta $e): string => $e->value, EtapaAbierta::cases())));
    }

    /** @return BelongsTo<Estado, $this> */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    /** @return BelongsTo<User, $this> */
    public function ejecutivo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ejecutivo_user_id');
    }

    /** @return BelongsTo<Moneda, $this> */
    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    /** @return BelongsTo<PartyContacto, $this> */
    public function decisor(): BelongsTo
    {
        return $this->belongsTo(PartyContacto::class, 'decisor_contacto_id');
    }

    /** @return HasMany<OportunidadCompetidor, $this> */
    public function competidores(): HasMany
    {
        return $this->hasMany(OportunidadCompetidor::class);
    }
}
