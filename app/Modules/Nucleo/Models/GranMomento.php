<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que se eligió para el gran momento de una fecha un año (plan 1.4d, D7): la variante y el versículo, los mismos para
 * todas las personas. Solo lo escribe GranMomentoService.
 *
 * @property int $id
 * @property Ocasion $ocasion
 * @property int $anio
 * @property string $variante
 * @property ?int $frase_saludo_id
 * @property int $created_by
 * @property ?int $updated_by
 */
class GranMomento extends Model
{
    use ConAuditoria;

    protected $table = 'grandes_momentos';

    protected $fillable = ['ocasion', 'anio', 'variante', 'frase_saludo_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ocasion' => Ocasion::class,
            'anio' => 'integer',
            'frase_saludo_id' => 'integer',
        ];
    }
}
