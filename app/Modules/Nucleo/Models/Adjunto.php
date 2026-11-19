<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * M7 · Un archivo adjunto a cualquier registro (plan 1.8). El archivo vive en el disco `adjuntos`; aquí su ruta,
 * su huella y de qué tipo es. Solo lo escribe `AdjuntosService`; quitarlo deja rastro (D2).
 *
 * @property int $id
 * @property string $adjuntable_type
 * @property int $adjuntable_id
 * @property ?int $tipo_adjunto_id
 * @property string $nombre_original
 * @property string $ruta
 * @property string $mime
 * @property int $tamano_bytes
 * @property string $hash
 * @property string $disco
 * @property ?GrupoSensible $grupo_sensible
 * @property ?int $reemplaza_a_adjunto_id
 * @property ?Carbon $quitado_at
 * @property ?int $quitado_por_user_id
 * @property int $created_by
 * @property ?Carbon $created_at
 */
class Adjunto extends Model
{
    use ConAuditoria;

    protected $table = 'adjuntos';

    protected $fillable = [
        'adjuntable_type', 'adjuntable_id', 'tipo_adjunto_id', 'nombre_original', 'ruta', 'mime', 'tamano_bytes', 'hash',
        'disco', 'grupo_sensible', 'reemplaza_a_adjunto_id', 'quitado_at', 'quitado_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
            'grupo_sensible' => GrupoSensible::class,
            'quitado_at' => 'datetime',
        ];
    }

    public function esSensible(): bool
    {
        return $this->grupo_sensible !== null;
    }

    /**
     * Quien lo subió.
     *
     * @return BelongsTo<User, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<TipoAdjunto, $this>
     */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoAdjunto::class, 'tipo_adjunto_id');
    }
}
