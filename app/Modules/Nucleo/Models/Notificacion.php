<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * M8 · Un aviso emitido a una persona (plan 1.8). Es la notificación de base de datos de Laravel sobre nuestra tabla:
 * la campana del panel la lee y la marca como leída. Lo crea solo `NotificacionesService`; el correo lo marca su job.
 *
 * @property int $id
 * @property string $type
 * @property string $notifiable_type
 * @property int $notifiable_id
 * @property array<string, mixed> $data
 * @property ?Carbon $read_at
 * @property string $clave_unica
 * @property bool $enviar_correo
 * @property ?Carbon $correo_reservado_hasta
 * @property ?Carbon $correo_enviado_at
 */
class Notificacion extends DatabaseNotification
{
    use ConAuditoria;

    protected $table = 'notificaciones';

    protected $keyType = 'int';

    public $incrementing = true;

    protected $fillable = ['type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'clave_unica', 'enviar_correo', 'correo_reservado_hasta', 'correo_enviado_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'enviar_correo' => 'boolean',
            'correo_reservado_hasta' => 'datetime',
            'correo_enviado_at' => 'datetime',
        ];
    }
}
