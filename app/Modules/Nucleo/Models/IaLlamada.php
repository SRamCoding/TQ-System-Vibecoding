<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Enums\ResultadoLlamadaIa;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * M12 · Una llamada al modelo de lenguaje (plan 1.11, D3), salga bien o no: también las inválidas, los reintentos y
 * los respaldos, porque todas se pagan. El tope mensual se mide desde aquí. Nunca guarda lo que se envió ni lo que
 * volvió: solo cuánto, cuánto tardó y cuánto costó.
 *
 * @property int $id
 * @property int $proceso_id
 * @property string $origen_type
 * @property int $origen_id
 * @property ?int $adjunto_id
 * @property int $modelo_id
 * @property bool $es_respaldo
 * @property int $intento
 * @property int $prompt_version
 * @property ResultadoLlamadaIa $resultado
 * @property int $tokens_entrada
 * @property int $tokens_salida
 * @property int $duracion_ms
 * @property ?int $precio_id
 * @property string $costo_usd
 * @property Carbon $created_at
 * @property-read IaModelo $modelo
 */
class IaLlamada extends Model implements DeclaraCamposSensibles
{
    use ConAuditoria;

    protected $table = 'ia_llamadas';

    protected $fillable = [
        'proceso_id', 'origen_type', 'origen_id', 'adjunto_id', 'modelo_id', 'es_respaldo', 'intento', 'prompt_version',
        'resultado', 'tokens_entrada', 'tokens_salida', 'duracion_ms', 'precio_id', 'costo_usd',
    ];

    protected function casts(): array
    {
        return [
            'resultado' => ResultadoLlamadaIa::class,
            'es_respaldo' => 'boolean',
            'costo_usd' => 'string',
        ];
    }

    /**
     * @return BelongsTo<IaModelo, $this>
     */
    public function modelo(): BelongsTo
    {
        return $this->belongsTo(IaModelo::class, 'modelo_id');
    }

    public function camposSensibles(): array
    {
        return [];
    }

    public function camposNoSensibles(): array
    {
        return [
            'precio_id' => 'El precio por token del servicio de IA, no el de un producto ni de una compra.',
            'costo_usd' => 'Lo que costó la llamada al servicio de IA: no es costo de un proyecto ni de un producto.',
        ];
    }
}
