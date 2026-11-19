<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Enums\AccionSugerenciaIa;
use App\Modules\Nucleo\Enums\EstadoSugerenciaIa;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * M12 · Lo que la IA propuso y qué hizo la persona (plan 1.11, D4, D6 y D9). Parte de un registro (y, si hay, de un
 * adjunto), trae cada campo con su evidencia y su seguridad, y apunta a la llamada que la produjo: de ahí salen el
 * proveedor, el modelo, la versión del prompt y el costo. Nunca guarda sola: una persona la acepta, corrige o rechaza.
 *
 * @property int $id
 * @property string $numero
 * @property int $proceso_id
 * @property int $llamada_id
 * @property string $origen_type
 * @property int $origen_id
 * @property ?int $origen_adjunto_id
 * @property array<string, mixed> $propuesta
 * @property array<string, string> $seguridad_por_campo
 * @property string $explicacion
 * @property EstadoSugerenciaIa $estado
 * @property ?AccionSugerenciaIa $accion
 * @property ?array<string, mixed> $correccion
 * @property ?int $resuelta_por
 * @property ?Carbon $resuelta_at
 * @property ?string $resultado_type
 * @property ?int $resultado_id
 * @property int $version
 * @property-read IaLlamada $llamada
 */
class IaSugerencia extends Model
{
    use ConAuditoria;

    protected $table = 'ia_sugerencias';

    protected $fillable = [
        'numero', 'proceso_id', 'llamada_id', 'origen_type', 'origen_id', 'origen_adjunto_id', 'propuesta',
        'seguridad_por_campo', 'explicacion', 'estado', 'accion', 'correccion', 'resuelta_por', 'resuelta_at',
        'resultado_type', 'resultado_id',
    ];

    protected function casts(): array
    {
        return [
            'propuesta' => 'array',
            'seguridad_por_campo' => 'array',
            'correccion' => 'array',
            'estado' => EstadoSugerenciaIa::class,
            'accion' => AccionSugerenciaIa::class,
            'resuelta_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<IaLlamada, $this>
     */
    public function llamada(): BelongsTo
    {
        return $this->belongsTo(IaLlamada::class, 'llamada_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function origen(): MorphTo
    {
        return $this->morphTo();
    }
}
