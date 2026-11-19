<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * M3 · Una fila de la bitácora de estados de un documento (plan 1.5, D2 y D9). Solo se inserta: nadie la corrige
 * ni la borra. La primera, la de la creación, no tiene `desde` ni transición.
 *
 * @property int $id
 * @property string $documentable_type
 * @property int $documentable_id
 * @property ?int $desde_estado_id
 * @property int $hacia_estado_id
 * @property ?int $transicion_id
 * @property bool $requiere_motivo
 * @property ?int $tipo_motivo_id
 * @property ?int $motivo_id
 * @property bool $motivo_es_otro
 * @property ?string $motivo_detalle
 * @property ?string $comentario
 * @property int $user_id
 * @property ?int $originado_por_user_id
 * @property CarbonImmutable $fecha
 */
class HistorialEstado extends Model
{
    public $timestamps = false;

    protected $table = 'historial_estados';

    protected $fillable = [
        'documentable_type', 'documentable_id', 'desde_estado_id', 'hacia_estado_id', 'transicion_id',
        'requiere_motivo', 'tipo_motivo_id', 'motivo_id', 'motivo_es_otro', 'motivo_detalle', 'comentario',
        'user_id', 'originado_por_user_id', 'fecha',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('El historial de estados no se modifica.'));
        static::deleting(fn (): never => throw new LogicException('El historial de estados no se borra.'));
    }

    /**
     * @return BelongsTo<Estado, $this>
     */
    public function desde(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'desde_estado_id');
    }

    /**
     * @return BelongsTo<Estado, $this>
     */
    public function hacia(): BelongsTo
    {
        return $this->belongsTo(Estado::class, 'hacia_estado_id');
    }

    /**
     * @return BelongsTo<TransicionEstado, $this>
     */
    public function transicion(): BelongsTo
    {
        return $this->belongsTo(TransicionEstado::class, 'transicion_id');
    }

    /**
     * @return BelongsTo<Motivo, $this>
     */
    public function motivo(): BelongsTo
    {
        return $this->belongsTo(Motivo::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function originadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'originado_por_user_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<EfectoAplicado, $this>
     */
    public function efectos(): HasMany
    {
        return $this->hasMany(EfectoAplicado::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requiere_motivo' => 'boolean',
            'motivo_es_otro' => 'boolean',
            'fecha' => 'immutable_datetime',
        ];
    }
}
