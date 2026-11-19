<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\PartySedeFactory;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * M1 · Sucursal, almacén o punto de entrega de una party (plan 1.3b, D3). La dirección fiscal no es una sede:
 * vive en la party. La dirección de una obra tampoco: es del proyecto (etapa 2). El nombre no se repite entre
 * las sedes activas de una party, sin mayúsculas ni acentos (D4), por `nombre_comparable`, que calcula la base
 * al guardar para que coincida siempre con su CHECK.
 *
 * @property int $id
 * @property int $party_id
 * @property string $nombre
 * @property string $nombre_comparable
 * @property string $direccion
 * @property ?int $ubigeo_id
 * @property ?string $latitud
 * @property ?string $longitud
 * @property ?int $contacto_id
 * @property ?string $codigo_establecimiento
 * @property bool $es_activa
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 * @property-read Party $party
 * @property-read ?Ubigeo $ubigeo
 * @property-read ?PartyContacto $contacto
 */
#[UseFactory(PartySedeFactory::class)]
class PartySede extends Model implements DescribibleEnBitacora
{
    /** @use HasFactory<PartySedeFactory> */
    use ConAuditoria, DescribeBitacora, HasFactory, RegistraCambios;

    protected $table = 'party_sedes';

    protected $fillable = [
        'party_id', 'nombre', 'direccion', 'ubigeo_id', 'latitud', 'longitud', 'contacto_id', 'codigo_establecimiento',
        'es_activa',
    ];

    protected static function booted(): void
    {
        static::saving(function (PartySede $sede): void {
            if ($sede->isDirty('nombre') || ! $sede->exists) {
                $sede->nombre_comparable = self::comparable($sede->nombre);
            }
        });
    }

    /** El nombre como lo compara la base: minúsculas y sin acentos (`lower(unaccent(…))`). */
    public static function comparable(string $nombre): string
    {
        return (string) DB::selectOne('SELECT lower(unaccent(?)) AS valor', [$nombre])->valor;
    }

    /**
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return ['version', 'nombre_comparable'];
    }

    public function etiquetasBitacora(): array
    {
        return [
            'party_id' => 'Persona o empresa',
            'nombre' => 'Nombre',
            'direccion' => 'Dirección',
            'ubigeo_id' => 'Ubigeo',
            'latitud' => 'Latitud',
            'longitud' => 'Longitud',
            'contacto_id' => 'Contacto',
            'codigo_establecimiento' => 'Código de establecimiento SUNAT',
            'es_activa' => 'Activa',
        ];
    }

    /** «Sede Almacén Chiclayo». */
    public function sujetoBitacora(): string
    {
        return 'Sede '.$this->nombre;
    }

    /**
     * «Agregó sede Almacén Chiclayo · Av. Principal 123».
     *
     * @param  array<string, mixed>  $valores
     */
    public function resumenCreacionBitacora(array $valores): ?string
    {
        return 'Agregó sede '.((string) ($valores['nombre'] ?? '')).' · '.((string) ($valores['direccion'] ?? ''));
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        return match ($campo) {
            'party_id' => Party::query()->whereKey($valor)->value('razon_social') ?? self::valorComun($valor),
            'ubigeo_id' => Ubigeo::query()->find($valor)?->etiqueta() ?? self::valorComun($valor),
            'contacto_id' => PartyContacto::query()->find($valor)?->nombreCompleto() ?? self::valorComun($valor),
            'es_activa' => self::valorComun((bool) $valor),
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
     * @return BelongsTo<Ubigeo, $this>
     */
    public function ubigeo(): BelongsTo
    {
        return $this->belongsTo(Ubigeo::class);
    }

    /**
     * @return BelongsTo<PartyContacto, $this>
     */
    public function contacto(): BelongsTo
    {
        return $this->belongsTo(PartyContacto::class, 'contacto_id');
    }

    /**
     * Coordenadas como texto (`decimal:7`): nunca `float` (regla 4).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'party_id' => 'integer',
            'ubigeo_id' => 'integer',
            'latitud' => 'decimal:7',
            'longitud' => 'decimal:7',
            'contacto_id' => 'integer',
            'es_activa' => 'boolean',
            'version' => 'integer',
        ];
    }
}
