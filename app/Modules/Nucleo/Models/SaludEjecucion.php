<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Enums\ResultadoEjecucion;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Una corrida de una tarea del programador (plan 1.12, D1): el latido, un respaldo, la revisión de huérfanos. La lee
 * la página de salud. `datos` lleva solo cantidades y tamaños (regla 10). La escribe `SaludService`.
 *
 * @property int $id
 * @property string $tarea
 * @property Carbon $iniciada_at
 * @property ?Carbon $terminada_at
 * @property ResultadoEjecucion $resultado
 * @property array<string, mixed> $datos
 */
class SaludEjecucion extends Model
{
    use ConAuditoria;

    protected $table = 'salud_ejecuciones';

    protected $fillable = ['tarea', 'iniciada_at', 'terminada_at', 'resultado', 'datos'];

    protected function casts(): array
    {
        return [
            'iniciada_at' => 'datetime',
            'terminada_at' => 'datetime',
            'resultado' => ResultadoEjecucion::class,
        ];
    }

    /**
     * Siempre un objeto JSON (CHECK `salud_ejecuciones_datos_check`): un arreglo vacío de PHP se guardaría como `[]`.
     *
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function datos(): Attribute
    {
        return Attribute::make(
            get: fn (?string $valor): array => $valor === null ? [] : (array) json_decode($valor, true),
            set: fn (array $valor): string => (string) json_encode((object) $valor, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }
}
