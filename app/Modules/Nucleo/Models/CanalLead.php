<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Enums\IconoCanal;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Model;

/**
 * Observación 9.3: el canal por el que llega un lead (Página web, WhatsApp, Instagram…), con su ícono. Distinto de la
 * campaña (la acción concreta) y del origen comercial de una oportunidad.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property IconoCanal $icono
 * @property bool $es_activo
 * @property int $version
 */
class CanalLead extends Model implements ElementoCatalogo
{
    use ConAuditoria, EsCatalogo;

    protected $table = 'canales_lead';

    protected $fillable = ['codigo', 'nombre', 'icono', 'es_activo'];

    protected function casts(): array
    {
        return ['icono' => IconoCanal::class, 'es_activo' => 'boolean'];
    }
}
