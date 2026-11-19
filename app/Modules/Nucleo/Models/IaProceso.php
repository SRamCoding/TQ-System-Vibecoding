<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * M12 · Lo editable de un proceso de IA (plan 1.11, D1): si está encendido, la autorización de Gerencia con la huella
 * de lo que autorizó y sus destinos externos (D2), el tope mensual en dólares (D3) y sus modelos (D5). El resto lo declara su módulo
 * (`DefinicionProcesoIa`). La base no deja encender sin autorización un proceso que envía datos fuera.
 *
 * @property int $id
 * @property string $codigo
 * @property bool $esta_activo
 * @property bool $envia_datos_externos
 * @property ?int $autorizado_por
 * @property ?Carbon $autorizado_at
 * @property ?string $autorizacion_huella
 * @property string $tope_mensual_usd
 * @property ?int $modelo_id
 * @property ?int $modelo_respaldo_id
 * @property int $version
 * @property-read ?IaModelo $modelo
 * @property-read ?IaModelo $modeloRespaldo
 * @property-read Collection<int, IaProcesoDestino> $destinos
 */
class IaProceso extends Model
{
    use ConAuditoria, RegistraCambios;

    protected $table = 'ia_procesos';

    protected $fillable = ['codigo', 'esta_activo', 'envia_datos_externos', 'autorizado_por', 'autorizado_at', 'autorizacion_huella', 'tope_mensual_usd', 'modelo_id', 'modelo_respaldo_id'];

    protected function casts(): array
    {
        return [
            'esta_activo' => 'boolean',
            'envia_datos_externos' => 'boolean',
            'autorizado_at' => 'datetime',
            'tope_mensual_usd' => 'string',
            'version' => 'integer',
        ];
    }

    public function camposSinBitacora(): array
    {
        return ['version'];
    }

    /**
     * @return BelongsTo<IaModelo, $this>
     */
    public function modelo(): BelongsTo
    {
        return $this->belongsTo(IaModelo::class, 'modelo_id');
    }

    /**
     * @return BelongsTo<IaModelo, $this>
     */
    public function modeloRespaldo(): BelongsTo
    {
        return $this->belongsTo(IaModelo::class, 'modelo_respaldo_id');
    }

    /**
     * Los proveedores externos autorizados (D2).
     *
     * @return HasMany<IaProcesoDestino, $this>
     */
    public function destinos(): HasMany
    {
        return $this->hasMany(IaProcesoDestino::class, 'proceso_id');
    }

    public function estaAutorizado(): bool
    {
        return $this->autorizado_por !== null;
    }
}
