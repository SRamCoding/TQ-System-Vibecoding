<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Un módulo que la persona sacó de su barra lateral (plan 1.4f, D2 y D4). Volver a mostrarlo borra la fila. Solo lo
 * escribe NavegacionService.
 *
 * @property int $id
 * @property int $user_id
 * @property string $clave_modulo
 * @property int $created_by
 * @property ?int $updated_by
 */
class ModuloOculto extends Model
{
    use ConAuditoria;

    protected $table = 'modulos_ocultos';

    protected $fillable = ['user_id', 'clave_modulo'];
}
