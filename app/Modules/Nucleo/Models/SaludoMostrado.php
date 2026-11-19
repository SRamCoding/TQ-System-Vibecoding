<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Enums\IdiomaSaludo;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * El saludo de una sesión de una persona (plan 1.4aa, D7): una fila por sesión (la semilla de `SemillaSesion`), con el
 * idioma en que se la saludó. Solo lo escribe y lo lee `HistorialSaludos`; no es una acción de la persona.
 *
 * @property int $id
 * @property int $user_id
 * @property IdiomaSaludo $idioma
 * @property string $sesion
 * @property CarbonImmutable $mostrado_at
 * @property int $created_by
 * @property ?int $updated_by
 */
class SaludoMostrado extends Model
{
    use ConAuditoria;

    protected $table = 'saludos_mostrados';

    protected $fillable = ['user_id', 'idioma', 'sesion', 'mostrado_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'idioma' => IdiomaSaludo::class,
            'mostrado_at' => 'immutable_datetime',
        ];
    }
}
