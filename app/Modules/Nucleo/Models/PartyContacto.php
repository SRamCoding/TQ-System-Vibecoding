<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\PartyContactoFactory;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * M1 · Un vínculo de contacto: esta persona en esta organización (plan 1.3b, D1). `hasta` es el último día
 * vigente; vacío, sigue ahí. Cuando alguien rota, no se borra: se cierra aquí y se abre otro vínculo que apunta
 * a este (`contacto_anterior_id`). Sus datos son personales: nunca en registros ni mensajes de error (regla 10).
 *
 * @property int $id
 * @property int $party_id
 * @property string $nombres
 * @property string $apellidos
 * @property ?string $cargo
 * @property ?string $area
 * @property ?string $telefono
 * @property ?string $email
 * @property bool $es_decisor
 * @property bool $es_principal
 * @property ?CarbonImmutable $desde
 * @property ?CarbonImmutable $hasta
 * @property ?int $contacto_anterior_id
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 * @property-read Party $party
 * @property-read ?PartyContacto $anterior
 * @property-read ?PartyContacto $siguiente
 */
#[UseFactory(PartyContactoFactory::class)]
class PartyContacto extends Model implements DescribibleEnBitacora
{
    /** @use HasFactory<PartyContactoFactory> */
    use ConAuditoria, DescribeBitacora, HasFactory, RegistraCambios;

    protected $table = 'party_contactos';

    protected $fillable = [
        'party_id', 'nombres', 'apellidos', 'cargo', 'area', 'telefono', 'email', 'es_decisor', 'es_principal',
        'desde', 'hasta', 'contacto_anterior_id',
    ];

    /** «Luis Fernández»: como se nombra a un contacto en la ficha. */
    public function nombreCompleto(): string
    {
        return $this->nombres.' '.$this->apellidos;
    }

    /**
     * Los vínculos vigentes: los que no tienen fecha de cierre. Única definición de «vigente» para contactos.
     * A diferencia de los roles, el día `hasta` ya no cuenta: cerrar un vínculo le quita en el acto la marca de
     * principal y lo saca de sus sedes, y `hasta` nunca es futuro (revisión de la 1.3b). `desde` es informativo.
     *
     * @param  Builder<static>  $consulta
     */
    public function scopeVigentes(Builder $consulta): void
    {
        $consulta->whereNull('hasta');
    }

    /**
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return ['version'];
    }

    public function etiquetasBitacora(): array
    {
        return [
            'party_id' => 'Persona o empresa',
            'nombres' => 'Nombres',
            'apellidos' => 'Apellidos',
            'cargo' => 'Cargo',
            'area' => 'Área',
            'telefono' => 'Teléfono',
            'email' => 'Correo',
            'es_decisor' => 'Decide la compra',
            'es_principal' => 'Principal',
            'desde' => 'Desde',
            'hasta' => 'Hasta',
            'contacto_anterior_id' => 'Viene de',
        ];
    }

    /** «Contacto Luis Fernández». Se lee dentro de la ficha de su party. */
    public function sujetoBitacora(): string
    {
        return 'Contacto '.$this->nombreCompleto();
    }

    /**
     * «Agregó contacto Luis Fernández · Jefe de Logística · principal».
     *
     * @param  array<string, mixed>  $valores
     */
    public function resumenCreacionBitacora(array $valores): ?string
    {
        $partes = array_filter([
            'Agregó contacto '.trim(((string) ($valores['nombres'] ?? '')).' '.((string) ($valores['apellidos'] ?? ''))),
            $valores['cargo'] ?? null,
            ($valores['es_principal'] ?? false) ? 'principal' : null,
            ($valores['es_decisor'] ?? false) ? 'decide la compra' : null,
            ($valores['contacto_anterior_id'] ?? null) === null ? null : 'viene de '.$this->valorLegibleBitacora('contacto_anterior_id', $valores['contacto_anterior_id']),
        ]);

        return implode(' · ', $partes);
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return match ($campo) {
            'desde', 'hasta' => $valor === null ? '—' : self::fechaLegible($valor),
            'party_id' => Party::query()->whereKey($valor)->value('razon_social') ?? self::valorComun($valor),
            'contacto_anterior_id' => $valor === null ? '—' : self::vinculoLegible((int) $valor),
            default => self::valorComun($valor),
        };
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * El vínculo cerrado del que viene este («antes en…»).
     *
     * @return BelongsTo<PartyContacto, $this>
     */
    public function anterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'contacto_anterior_id');
    }

    /**
     * El vínculo que continúa a este («ahora en…»).
     *
     * @return HasOne<PartyContacto, $this>
     */
    public function siguiente(): HasOne
    {
        return $this->hasOne(self::class, 'contacto_anterior_id');
    }

    /** «Municipalidad de Pimentel»: el vínculo anterior se reconoce por su organización. */
    private static function vinculoLegible(int $id): string
    {
        $contacto = self::query()->with('party:id,razon_social')->find($id);

        return $contacto === null ? (string) $id : $contacto->party->razon_social;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'party_id' => 'integer',
            'es_decisor' => 'boolean',
            'es_principal' => 'boolean',
            'desde' => 'immutable_date',
            'hasta' => 'immutable_date',
            'contacto_anterior_id' => 'integer',
            'version' => 'integer',
        ];
    }
}
