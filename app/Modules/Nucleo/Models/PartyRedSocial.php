<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\PartyRedSocialFactory;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M1 · Una red social de una party, o de uno de sus contactos si lleva `contacto_id` (plan 1.4o, D2). La de una
 * persona del equipo puede ser privada (D3): solo la ve ella. Se escribe
 * por RedesSocialesService.
 *
 * @property int $id
 * @property int $party_id
 * @property ?int $contacto_id
 * @property int $red_social_id
 * @property ?string $detalle
 * @property string $enlace
 * @property bool $es_privada
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 * @property-read Party $party
 * @property-read ?PartyContacto $contacto
 * @property-read RedSocial $redSocial
 */
#[UseFactory(PartyRedSocialFactory::class)]
class PartyRedSocial extends Model implements DescribibleEnBitacora
{
    /** @use HasFactory<PartyRedSocialFactory> */
    use ConAuditoria, DescribeBitacora, HasFactory, RegistraCambios;

    protected $table = 'party_redes_sociales';

    /** El nombre que se muestra: con «Otra red», cuál es (plan 2.0, D5). */
    public function nombreDeLaRed(): string
    {
        return $this->detalle ?? $this->redSocial->nombre;
    }

    protected $fillable = ['party_id', 'contacto_id', 'red_social_id', 'detalle', 'enlace', 'es_privada'];

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
            'contacto_id' => 'Contacto',
            'red_social_id' => 'Red',
            'detalle' => 'Cuál red',
            'enlace' => 'Enlace',
            'es_privada' => 'Solo la ve la persona',
        ];
    }

    /** «Red LinkedIn». */
    public function sujetoBitacora(): string
    {
        return 'Red '.(RedSocial::query()->whereKey($this->red_social_id)->value('nombre') ?? '');
    }

    /**
     * «Agregó red LinkedIn», sin el enlace: es un dato personal (regla 10) y se ve en la pestaña.
     *
     * @param  array<string, mixed>  $valores
     */
    public function resumenCreacionBitacora(array $valores): ?string
    {
        return 'Agregó '.mb_lcfirst($this->sujetoBitacora());
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        return match ($campo) {
            'party_id' => Party::query()->whereKey($valor)->value('razon_social') ?? self::valorComun($valor),
            'contacto_id' => PartyContacto::query()->find($valor)?->nombreCompleto() ?? self::valorComun($valor),
            'red_social_id' => RedSocial::query()->find($valor)?->etiqueta() ?? self::valorComun($valor),
            'es_privada' => self::valorComun((bool) $valor),
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
     * @return BelongsTo<PartyContacto, $this>
     */
    public function contacto(): BelongsTo
    {
        return $this->belongsTo(PartyContacto::class, 'contacto_id');
    }

    /**
     * @return BelongsTo<RedSocial, $this>
     */
    public function redSocial(): BelongsTo
    {
        return $this->belongsTo(RedSocial::class);
    }

    protected function casts(): array
    {
        return [
            'party_id' => 'integer',
            'contacto_id' => 'integer',
            'red_social_id' => 'integer',
            'es_privada' => 'boolean',
            'version' => 'integer',
        ];
    }
}
