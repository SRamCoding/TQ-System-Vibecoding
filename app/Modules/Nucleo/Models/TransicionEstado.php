<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\CodigoInmutable;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M3 · Un salto permitido entre dos estados del mismo documento (plan 1.5, D1 y D2). Lo escribe el sincronizador;
 * no se edita por pantalla. Sin `permiso`, la ejecuta el sistema: o es del sistema (la pide código del módulo) o
 * requiere aprobación (la ejecuta el mecanismo de aprobaciones al completarse las firmas).
 *
 * @property int $id
 * @property string $documento_type
 * @property string $codigo
 * @property string $nombre
 * @property int $orden
 * @property int $desde_estado_id
 * @property int $hacia_estado_id
 * @property bool $hacia_es_cancelacion
 * @property bool $requiere_motivo
 * @property ?int $tipo_motivo_id
 * @property bool $requiere_aprobacion
 * @property ?string $permiso
 * @property bool $es_activa
 */
class TransicionEstado extends Model
{
    use CodigoInmutable, ConAuditoria;

    protected $table = 'transiciones_estado';

    protected $fillable = [
        'documento_type', 'codigo', 'nombre', 'orden', 'desde_estado_id', 'hacia_estado_id', 'hacia_es_cancelacion',
        'requiere_motivo', 'tipo_motivo_id', 'requiere_aprobacion', 'permiso', 'es_activa',
    ];

    /**
     * @return list<string>
     */
    public function columnasInmutables(): array
    {
        return ['documento_type', 'codigo', 'desde_estado_id'];
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
     * @return BelongsTo<TipoMotivo, $this>
     */
    public function tipoMotivo(): BelongsTo
    {
        return $this->belongsTo(TipoMotivo::class);
    }

    /** La pide código del módulo. Una con aprobación tampoco tiene permiso, pero no es de estas. */
    public function esDelSistema(): bool
    {
        return $this->permiso === null && ! $this->requiere_aprobacion;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'hacia_es_cancelacion' => 'boolean',
            'requiere_motivo' => 'boolean',
            'requiere_aprobacion' => 'boolean',
            'es_activa' => 'boolean',
        ];
    }
}
