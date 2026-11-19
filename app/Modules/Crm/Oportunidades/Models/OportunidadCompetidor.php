<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Models;

use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\CodigoInmutable;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contra quién compite una oportunidad y a qué precio (plan 2.21, D4): una ficha de Personas y empresas, nunca texto
 * libre. El ganador lleva `es_ganador` (uno por oportunidad) y su precio, o «precio desconocido».
 *
 * @property int $id
 * @property int $oportunidad_id
 * @property int $party_id
 * @property ?string $precio
 * @property ?int $moneda_id
 * @property bool $precio_desconocido
 * @property bool $es_ganador
 * @property ?string $fortalezas
 * @property ?string $debilidades
 * @property int $version
 * @property-read Party $party
 */
class OportunidadCompetidor extends Model implements DeclaraCamposSensibles, DescribibleEnBitacora
{
    use CodigoInmutable, ConAuditoria, DescribeBitacora, RegistraCambios;

    protected $table = 'oportunidad_competidores';

    protected $fillable = ['oportunidad_id', 'party_id', 'precio', 'moneda_id', 'precio_desconocido', 'es_ganador', 'fortalezas', 'debilidades'];

    protected function casts(): array
    {
        return [
            'oportunidad_id' => 'integer',
            'party_id' => 'integer',
            'precio' => 'decimal:2',
            'moneda_id' => 'integer',
            'precio_desconocido' => 'boolean',
            'es_ganador' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function columnasInmutables(): array
    {
        return ['oportunidad_id', 'party_id'];
    }

    public function camposSensibles(): array
    {
        return [];
    }

    public function camposNoSensibles(): array
    {
        return [
            'precio' => 'El precio que ofertó o ganó un competidor: dato comercial que alimenta el histórico de precios, no un dato personal.',
            'precio_desconocido' => 'Marca que el precio del competidor ganador no se conoce.',
        ];
    }

    public function camposSinBitacora(): array
    {
        return ['version'];
    }

    public function etiquetasBitacora(): array
    {
        return [
            'oportunidad_id' => 'Oportunidad', 'party_id' => 'Competidor', 'precio' => 'Precio', 'moneda_id' => 'Moneda',
            'precio_desconocido' => 'Precio desconocido', 'es_ganador' => 'Ganó', 'fortalezas' => 'Fortalezas', 'debilidades' => 'Debilidades',
        ];
    }

    public function sujetoBitacora(): string
    {
        return 'Competidor';
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return match (true) {
            $valor === null => '—',
            $campo === 'party_id' => Party::query()->whereKey($valor)->value('razon_social') ?? self::valorComun($valor),
            $campo === 'moneda_id' => Moneda::query()->whereKey($valor)->value('codigo') ?? self::valorComun($valor),
            default => self::valorComun($valor),
        };
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return BelongsTo<Oportunidad, $this> */
    public function oportunidad(): BelongsTo
    {
        return $this->belongsTo(Oportunidad::class);
    }
}
