<?php

declare(strict_types=1);

namespace App\Modules\Erp\Compras\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** MVP rápido (t3-compras). */
class Requerimiento extends Model
{
    use ConAuditoria;

    protected $table = 'requerimientos';

    protected $fillable = ['codigo', 'proyecto_id', 'solicitante_user_id', 'descripcion', 'fecha_necesidad', 'estado', 'motivo_exceso', 'comentario_rechazo', 'aprobado_por', 'aprobado_at'];

    protected function casts(): array
    {
        return ['fecha_necesidad' => 'immutable_date', 'aprobado_at' => 'immutable_datetime'];
    }

    /** @return HasMany<RequerimientoLinea, $this> */
    public function lineas(): HasMany
    {
        return $this->hasMany(RequerimientoLinea::class)->orderBy('orden');
    }

    /** @return BelongsTo<User, $this> */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_user_id');
    }
}
