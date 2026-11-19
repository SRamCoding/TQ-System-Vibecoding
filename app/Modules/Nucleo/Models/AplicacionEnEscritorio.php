<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Una aplicación que una persona abre en su programa de escritorio, no en la web (plan 1.4z, D6): de Techquk o propia,
 * nunca las dos. Sin fila, en la web. Solo la escribe AplicacionesService::abrirEn.
 *
 * @property int $id
 * @property int $user_id
 * @property ?int $aplicacion_id
 * @property ?int $aplicacion_propia_id
 */
class AplicacionEnEscritorio extends Model
{
    use ConAuditoria;

    protected $table = 'aplicaciones_en_escritorio';

    protected $fillable = ['user_id', 'aplicacion_id', 'aplicacion_propia_id'];
}
