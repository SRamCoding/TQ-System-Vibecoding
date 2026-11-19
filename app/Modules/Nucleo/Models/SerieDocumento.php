<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * M6 · El contador de una serie en un ámbito y un periodo (plan 1.6). Solo lo escribe `NumeracionService`, con una
 * sola sentencia que crea o suma y bloquea la fila hasta el final de la transacción. Este modelo es para leerlo.
 *
 * @property int $id
 * @property string $documento_type
 * @property string $serie
 * @property string $ambito
 * @property string $periodo
 * @property int $ultimo_numero
 */
class SerieDocumento extends Model
{
    use ConAuditoria;

    protected $table = 'series_documento';

    protected $fillable = [];

    protected function casts(): array
    {
        return ['ultimo_numero' => 'integer'];
    }
}
