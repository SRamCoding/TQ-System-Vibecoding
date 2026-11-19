<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Cuántas veces abrió una persona una aplicación desde el panel (plan 1.4p, D5): de Techquk o propia, nunca las dos.
 * Decide las 3 del muelle. Solo lo escribe AplicacionesService::registrarUso, con un INSERT … ON CONFLICT.
 *
 * @property int $id
 * @property int $user_id
 * @property ?int $aplicacion_id
 * @property ?int $aplicacion_propia_id
 * @property int $usos
 * @property CarbonImmutable $ultimo_uso_at
 */
class UsoAplicacion extends Model
{
    use ConAuditoria;

    protected $table = 'usos_aplicacion';

    protected $fillable = ['user_id', 'aplicacion_id', 'aplicacion_propia_id', 'usos', 'ultimo_uso_at'];

    protected function casts(): array
    {
        return [
            'usos' => 'integer',
            'ultimo_uso_at' => 'immutable_datetime',
        ];
    }
}
