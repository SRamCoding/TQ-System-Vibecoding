<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Una oportunidad que una persona puso en su tablero (plan 2.21d, D5). Solo la usa `TableroDeBotonera`.
 *
 * @property int $id
 * @property int $user_id
 * @property int $oportunidad_id
 */
class OportunidadEnTablero extends Model
{
    use ConAuditoria;

    protected $table = 'oportunidades_en_tablero';

    protected $fillable = ['user_id', 'oportunidad_id'];
}
