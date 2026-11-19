<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use stdClass;

/**
 * M3 · Un efecto aplicado en un cambio de estado, con lo que hizo (`datos`) para revertir con eso (plan 1.5, D3).
 * Parte de la bitácora: solo cambian `ejecutado_at` (cuando el job corre un externo), `revertido_por_historial_id`
 * (al anular) y `reversion_ejecutada_at` (cuando el job revierte un externo), una vez cada una, y las reservas de
 * los externos (`reservado_hasta`, `reversion_reservada_hasta`), que van y vienen; no se borra. `datos` no se muestra
 * tal cual (regla 10).
 *
 * @property int $id
 * @property int $historial_estado_id
 * @property string $clave
 * @property int $orden
 * @property bool $es_externo
 * @property array<string, mixed> $datos
 * @property ?CarbonImmutable $ejecutado_at
 * @property ?int $revertido_por_historial_id
 * @property ?CarbonImmutable $reversion_ejecutada_at
 * @property ?CarbonImmutable $reservado_hasta
 * @property ?CarbonImmutable $reversion_reservada_hasta
 */
class EfectoAplicado extends Model
{
    public $timestamps = false;

    protected $table = 'efectos_aplicados';

    protected $fillable = ['historial_estado_id', 'clave', 'orden', 'es_externo', 'datos', 'ejecutado_at', 'revertido_por_historial_id', 'reversion_ejecutada_at',
        'reservado_hasta', 'reversion_reservada_hasta'];

    /** Se ponen una sola vez. */
    private const MARCAS = ['ejecutado_at', 'revertido_por_historial_id', 'reversion_ejecutada_at'];

    /** Van y vienen mientras un job intenta. */
    private const RESERVAS = ['reservado_hasta', 'reversion_reservada_hasta'];

    protected $hidden = ['datos'];

    protected static function booted(): void
    {
        static::updating(function (self $efecto): void {
            $cambiadas = array_values(array_diff(array_keys($efecto->getDirty()), self::RESERVAS));
            $remarcadas = array_filter($cambiadas, fn (string $columna): bool => $efecto->getOriginal($columna) !== null);

            if (array_diff($cambiadas, self::MARCAS) !== [] || $remarcadas !== []) {
                throw new LogicException('Un efecto aplicado solo se marca ejecutado o revertido, una vez.');
            }
        });
        static::deleting(fn (): never => throw new LogicException('Un efecto aplicado no se borra.'));
    }

    /**
     * @return BelongsTo<HistorialEstado, $this>
     */
    public function historial(): BelongsTo
    {
        return $this->belongsTo(HistorialEstado::class, 'historial_estado_id');
    }

    /**
     * Siempre un objeto JSON, aunque el efecto no devuelva nada (la base lo exige).
     *
     * @return Attribute<never, array<mixed>>
     */
    protected function datos(): Attribute
    {
        return Attribute::set(fn (array $datos): string => (string) json_encode($datos === [] ? new stdClass : $datos, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'es_externo' => 'boolean',
            'datos' => 'array',
            'ejecutado_at' => 'immutable_datetime',
            'reversion_ejecutada_at' => 'immutable_datetime',
            'reservado_hasta' => 'immutable_datetime',
            'reversion_reservada_hasta' => 'immutable_datetime',
        ];
    }
}
