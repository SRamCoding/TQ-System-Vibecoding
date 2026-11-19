<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Enums\EventoAuditoria;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Una fila de la bitácora de cambios (M4). Solo se inserta: nadie la corrige ni la borra.
 *
 * @property int $id
 * @property string $auditable_type
 * @property int $auditable_id
 * @property EventoAuditoria $evento
 * @property int $user_id
 * @property ?array<string, mixed> $valores_anteriores
 * @property ?array<string, mixed> $valores_nuevos
 * @property ?string $ip
 * @property ?string $agente
 * @property CarbonImmutable $fecha
 */
class AuditoriaCambio extends Model
{
    public $timestamps = false;

    protected $table = 'auditoria_cambios';

    protected $fillable = [
        'auditable_type', 'auditable_id', 'evento', 'user_id',
        'valores_anteriores', 'valores_nuevos', 'ip', 'agente', 'fecha',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('La bitácora de cambios no se modifica.'));
        static::deleting(fn (): never => throw new LogicException('La bitácora de cambios no se borra.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'evento' => EventoAuditoria::class,
            'valores_anteriores' => 'array',
            'valores_nuevos' => 'array',
            'fecha' => 'immutable_datetime',
        ];
    }
}
