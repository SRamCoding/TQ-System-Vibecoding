<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * Que una persona vio el gran momento de un año (plan 1.4d, D4). Su `created_at` es la marca. Solo lo escribe
 * GranMomentoService, con la acción `nucleo.marcar-momento-visto`.
 *
 * @property int $id
 * @property int $user_id
 * @property int $gran_momento_id
 * @property int $created_by
 * @property ?int $updated_by
 */
class MomentoVisto extends Model
{
    use ConAuditoria;

    protected $table = 'momentos_vistos';

    protected $fillable = ['user_id', 'gran_momento_id'];
}
