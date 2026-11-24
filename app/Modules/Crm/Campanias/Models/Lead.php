<?php

declare(strict_types=1);

namespace App\Modules\Crm\Campanias\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** MVP rápido (t1-campanias): un contacto entrante sin calificar. */
class Lead extends Model
{
    use ConAuditoria;

    public const array ESTADOS = ['nuevo' => 'Sin atender', 'contactado' => 'Contactado', 'convertido' => 'Convertido', 'descartado' => 'Descartado'];

    public const array COLORES = ['nuevo' => 'warning', 'contactado' => 'info', 'convertido' => 'success', 'descartado' => 'gray'];

    protected $table = 'leads';

    protected $fillable = ['campania_id', 'origen_comercial_id', 'canal_lead_id', 'ya_registrado', 'registrado_como', 'nombre_contacto', 'cargo', 'organizacion', 'ruc', 'email', 'telefono', 'mensaje', 'cuenta_id', 'oportunidad_id', 'estado', 'motivo_descarte', 'asignado_user_id', 'atendido_at'];

    protected function casts(): array
    {
        return ['atendido_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Campania, $this> */
    public function campania(): BelongsTo
    {
        return $this->belongsTo(Campania::class);
    }

    /** @return BelongsTo<User, $this> */
    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_user_id');
    }
}
