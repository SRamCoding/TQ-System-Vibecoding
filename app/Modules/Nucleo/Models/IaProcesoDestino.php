<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

/**
 * M12 · Un proveedor externo adonde Gerencia autorizó que salgan los datos de un proceso (plan 1.11, D2). Existe solo
 * mientras dura la autorización: lleva su huella, y la base no deja quitarla sin borrar antes los destinos.
 *
 * @property int $id
 * @property int $proceso_id
 * @property int $proveedor_id
 * @property string $autorizacion_huella
 */
class IaProcesoDestino extends Model
{
    use ConAuditoria;

    protected $table = 'ia_proceso_destinos';

    protected $fillable = ['proceso_id', 'proveedor_id', 'autorizacion_huella'];
}
