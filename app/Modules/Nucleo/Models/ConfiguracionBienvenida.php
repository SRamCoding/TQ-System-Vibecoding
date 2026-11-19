<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Illuminate\Database\Eloquent\Model;

/**
 * Si una persona apagó la bienvenida de la configuración inicial, o la volvió a encender (plan 1.13b, D1). El historial
 * de cambios guarda quién y cuándo.
 *
 * @property int $id
 * @property int $user_id
 * @property bool $es_apagada
 */
class ConfiguracionBienvenida extends Model
{
    use ConAuditoria, RegistraCambios;

    protected $table = 'configuracion_bienvenidas';

    protected $fillable = ['user_id', 'es_apagada'];

    protected function casts(): array
    {
        return ['es_apagada' => 'boolean'];
    }

    public function camposSinBitacora(): array
    {
        return [];
    }
}
