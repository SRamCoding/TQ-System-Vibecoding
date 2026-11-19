<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Models;

use App\Modules\Crm\Cuentas\database\factories\CuentaFactory;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\NivelGobierno;
use App\Modules\Nucleo\Models\OrigenComercial;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La ficha comercial de una persona o empresa (plan 2.20, D1): una por ficha. Solo la escribe CuentasService. Las
 * relaciones con modelos del núcleo son solo para leer (ARCHITECTURE.md, regla 1 de dependencias): la ficha y sus
 * tipos se cambian por los servicios del núcleo.
 *
 * @property int $id
 * @property int $party_id
 * @property int $ejecutivo_user_id
 * @property int $origen_comercial_id
 * @property ?string $origen_detalle
 * @property ?int $nivel_gobierno_id
 * @property ?CarbonImmutable $cliente_desde
 * @property int $version
 * @property ?string $notas
 * @property bool $es_activa
 * @property ?int $motivo_inactiva_id
 * @property ?string $motivo_inactiva_detalle
 * @property CarbonImmutable $created_at
 * @property int $created_by
 * @property-read Party $party
 * @property-read User $ejecutivo
 * @property-read OrigenComercial $origenComercial
 * @property-read ?NivelGobierno $nivelGobierno
 * @property-read ?Motivo $motivoInactiva
 */
#[UseFactory(CuentaFactory::class)]
class Cuenta extends Model implements DescribibleEnBitacora
{
    /** @use HasFactory<CuentaFactory> */
    use ConAuditoria, DescribeBitacora, HasFactory, RegistraCambios;

    protected $table = 'cuentas';

    protected $fillable = [
        'party_id', 'ejecutivo_user_id', 'origen_comercial_id', 'origen_detalle', 'nivel_gobierno_id', 'cliente_desde', 'notas',
        'es_activa', 'motivo_inactiva_id', 'motivo_inactiva_detalle',
    ];

    protected function casts(): array
    {
        return [
            'party_id' => 'integer',
            'ejecutivo_user_id' => 'integer',
            'origen_comercial_id' => 'integer',
            'nivel_gobierno_id' => 'integer',
            'cliente_desde' => 'immutable_date',
            'version' => 'integer',
            'es_activa' => 'boolean',
            'motivo_inactiva_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function camposSinBitacora(): array
    {
        return ['version'];
    }

    /** D10: el historial se lee como en el diseño, con nombres y valores legibles. */
    public function etiquetasBitacora(): array
    {
        return [
            'party_id' => 'Empresa', 'ejecutivo_user_id' => 'Ejecutivo', 'origen_comercial_id' => 'Origen', 'origen_detalle' => 'Detalle del origen',
            'nivel_gobierno_id' => 'Nivel de gobierno', 'cliente_desde' => 'Cliente desde', 'notas' => 'Notas',
            'es_activa' => 'Activa', 'motivo_inactiva_id' => 'Motivo de inactiva', 'motivo_inactiva_detalle' => 'Detalle del motivo',
        ];
    }

    public function sujetoBitacora(): string
    {
        return 'Cuenta';
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return match (true) {
            $valor === null => '—',
            $campo === 'party_id' => Party::query()->whereKey($valor)->value('razon_social') ?? self::valorComun($valor),
            $campo === 'ejecutivo_user_id' => User::query()->whereKey($valor)->value('name') ?? self::valorComun($valor),
            $campo === 'origen_comercial_id' => OrigenComercial::query()->whereKey($valor)->value('nombre') ?? self::valorComun($valor),
            $campo === 'nivel_gobierno_id' => NivelGobierno::query()->whereKey($valor)->value('nombre') ?? self::valorComun($valor),
            $campo === 'motivo_inactiva_id' => Motivo::query()->whereKey($valor)->value('nombre') ?? self::valorComun($valor),
            $campo === 'cliente_desde' => CarbonImmutable::parse((string) $valor)->format('d/m/Y'),
            default => self::valorComun($valor),
        };
    }

    /**
     * Solo para leer (regla 2): la ficha la cambian los servicios del núcleo.
     *
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return BelongsTo<User, $this> */
    public function ejecutivo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ejecutivo_user_id');
    }

    /** @return BelongsTo<NivelGobierno, $this> */
    public function nivelGobierno(): BelongsTo
    {
        return $this->belongsTo(NivelGobierno::class);
    }

    /** @return BelongsTo<OrigenComercial, $this> */
    public function origenComercial(): BelongsTo
    {
        return $this->belongsTo(OrigenComercial::class);
    }

    /** @return BelongsTo<Motivo, $this> */
    public function motivoInactiva(): BelongsTo
    {
        return $this->belongsTo(Motivo::class, 'motivo_inactiva_id');
    }

    /** @return HasMany<CuentaMarcaCliente, $this> */
    public function marcasCliente(): HasMany
    {
        return $this->hasMany(CuentaMarcaCliente::class);
    }
}
