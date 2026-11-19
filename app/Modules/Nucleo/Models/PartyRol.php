<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\PartyRolFactory;
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

/**
 * M1 · Un periodo en que una party cumple un rol. `hasta` es el último día vigente; vacío, sin fin (plan 1.3,
 * D8). La base impide que dos periodos del mismo rol de una party se crucen (D2).
 *
 * @property int $id
 * @property int $party_id
 * @property int $rol_party_id
 * @property CarbonImmutable $desde
 * @property ?CarbonImmutable $hasta
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 * @property-read Party $party
 * @property-read RolParty $rol
 */
#[UseFactory(PartyRolFactory::class)]
class PartyRol extends Model implements DescribibleEnBitacora
{
    /** @use HasFactory<PartyRolFactory> */
    use ConAuditoria, DescribeBitacora, HasFactory, RegistraCambios;

    protected $table = 'party_roles';

    protected $fillable = ['party_id', 'rol_party_id', 'desde', 'hasta'];

    /**
     * Los periodos vigentes en el día (`Y-m-d`). Única definición de «vigente»: el día `hasta` todavía cuenta,
     * como en la restricción de la base (`daterange(desde, hasta, '[]')`).
     *
     * @param  Builder<static>  $consulta
     */
    public function scopeVigenteEl(Builder $consulta, string $dia): void
    {
        $consulta->where('desde', '<=', $dia)
            ->where(fn (Builder $q): Builder => $q->whereNull('hasta')->orWhere('hasta', '>=', $dia));
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
        return ['party_id' => 'Persona o empresa', 'rol_party_id' => 'Rol', 'desde' => 'Desde', 'hasta' => 'Hasta'];
    }

    /** «Rol Cliente». Un periodo se lee junto con la party: la pestaña de historial de su ficha. */
    public function sujetoBitacora(): string
    {
        return 'Rol '.$this->rol->nombre;
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    public function resumenCreacionBitacora(array $valores): ?string
    {
        $resumen = 'Asignó rol '.$this->valorLegibleBitacora('rol_party_id', $valores['rol_party_id'] ?? null)
            .' desde '.$this->valorLegibleBitacora('desde', $valores['desde'] ?? null);

        return ($valores['hasta'] ?? null) === null ? $resumen : $resumen.' hasta '.$this->valorLegibleBitacora('hasta', $valores['hasta']);
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return match ($campo) {
            'hasta' => $valor === null ? 'sin fin' : self::fechaLegible($valor),
            'desde' => self::fechaLegible($valor),
            'rol_party_id' => RolParty::query()->whereKey($valor)->value('nombre') ?? self::valorComun($valor),
            'party_id' => Party::query()->whereKey($valor)->value('razon_social') ?? self::valorComun($valor),
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
     * @return BelongsTo<RolParty, $this>
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(RolParty::class, 'rol_party_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'party_id' => 'integer',
            'rol_party_id' => 'integer',
            'desde' => 'immutable_date',
            'hasta' => 'immutable_date',
            'version' => 'integer',
        ];
    }
}
