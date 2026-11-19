<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Una preferencia que la persona cambió (plan 1.4g, D2). Volver al defecto borra la fila. Solo la escribe
 * PreferenciasUsuarioService.
 *
 * @property int $id
 * @property int $user_id
 * @property string $clave
 * @property string $valor
 * @property int $created_by
 * @property ?int $updated_by
 */
class PreferenciaUsuario extends Model
{
    use ConAuditoria;

    protected $table = 'preferencias_usuario';

    protected $fillable = ['user_id', 'clave', 'valor'];
}
