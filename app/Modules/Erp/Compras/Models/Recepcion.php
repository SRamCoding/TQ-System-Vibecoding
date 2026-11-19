<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;

/** MVP rápido (t3-compras). */
class Recepcion extends Model
{
    use ConAuditoria;

    protected $table = 'recepciones';

    protected $fillable = ['codigo', 'orden_id', 'fecha', 'guia', 'lugar', 'observacion'];

    protected function casts(): array
    {
        return ['fecha' => 'immutable_date'];
    }
}
