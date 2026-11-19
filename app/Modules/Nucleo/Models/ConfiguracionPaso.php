<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Illuminate\Database\Eloquent\Model;

/**
 * Un paso del asistente de configuración inicial que el administrador dio por listo, o reabrió (plan 1.13, D3). El
 * historial de cambios guarda quién y cuándo.
 *
 * @property int $id
 * @property string $paso
 * @property bool $es_listo
 */
class ConfiguracionPaso extends Model
{
    use ConAuditoria, RegistraCambios;

    protected $table = 'configuracion_pasos';

    protected $fillable = ['paso', 'es_listo'];

    protected function casts(): array
    {
        return ['es_listo' => 'boolean'];
    }

    public function camposSinBitacora(): array
    {
        return [];
    }
}
