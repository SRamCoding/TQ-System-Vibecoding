<?php

declare(strict_types=1);

namespace App\Modules\Crm\Actividades\Models;

use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MVP rápido (t1-agenda): una gestión con el cliente (realizada) o la próxima acción (pendiente).
 *
 * @property int $id
 * @property string $tipo
 * @property int $cuenta_id
 * @property ?int $oportunidad_id
 * @property string $asunto
 * @property CarbonImmutable $fecha
 * @property ?string $resultado
 * @property bool $es_realizada
 * @property int $responsable_user_id
 */
class Actividad extends Model
{
    use ConAuditoria;

    public const array TIPOS = ['visita' => 'Visita', 'llamada' => 'Llamada', 'correo' => 'Correo', 'reunion' => 'Reunión'];

    public const array ICONOS = ['visita' => 'heroicon-o-map-pin', 'llamada' => 'heroicon-o-phone', 'correo' => 'heroicon-o-envelope', 'reunion' => 'heroicon-o-users'];

    protected $table = 'actividades';

    protected $fillable = ['tipo', 'cuenta_id', 'oportunidad_id', 'asunto', 'fecha', 'resultado', 'es_realizada', 'responsable_user_id'];

    protected function casts(): array
    {
        return ['fecha' => 'immutable_datetime', 'es_realizada' => 'boolean', 'version' => 'integer'];
    }

    /** @return BelongsTo<Cuenta, $this> */
    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class);
    }

    /** @return BelongsTo<Oportunidad, $this> */
    public function oportunidad(): BelongsTo
    {
        return $this->belongsTo(Oportunidad::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }
}
