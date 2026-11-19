<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\CruceFuncionesFactory;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * M10 · Una persona que tiene los dos permisos de un par incompatible (plan 1.4, D9). Abierto mientras los tenga;
 * se cierra (`hasta`), nunca se borra. Está controlado si tiene un control actual y vigente; si no, pendiente.
 *
 * @property int $id
 * @property int $user_id
 * @property int $incompatibilidad_id
 * @property CarbonImmutable $desde
 * @property ?CarbonImmutable $hasta
 * @property ?int $aprobado_por_user_id
 * @property int $created_by
 * @property ?int $updated_by
 * @property-read User $usuario
 * @property-read IncompatibilidadPermisos $incompatibilidad
 * @property-read ?ControlCruce $controlActual
 */
#[UseFactory(CruceFuncionesFactory::class)]
class CruceFunciones extends Model implements DescribibleEnBitacora
{
    /** @use HasFactory<CruceFuncionesFactory> */
    use ConAuditoria, DescribeBitacora, HasFactory, RegistraCambios;

    protected $table = 'cruces_funciones';

    protected $fillable = ['user_id', 'incompatibilidad_id', 'desde', 'hasta', 'aprobado_por_user_id'];

    /**
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return [];
    }

    public function etiquetasBitacora(): array
    {
        return [
            'user_id' => 'Usuario', 'incompatibilidad_id' => 'Permisos incompatibles', 'desde' => 'Desde',
            'hasta' => 'Hasta', 'aprobado_por_user_id' => 'Aprobado por',
        ];
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return match ($campo) {
            'desde' => self::fechaLegible($valor),
            'hasta' => $valor === null ? 'abierto' : self::fechaLegible($valor),
            'user_id', 'aprobado_por_user_id' => User::query()->whereKey($valor)->value('name') ?? self::valorComun($valor),
            default => self::valorComun($valor),
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<IncompatibilidadPermisos, $this>
     */
    public function incompatibilidad(): BelongsTo
    {
        return $this->belongsTo(IncompatibilidadPermisos::class, 'incompatibilidad_id');
    }

    /**
     * @return HasMany<ControlCruce, $this>
     */
    public function controles(): HasMany
    {
        return $this->hasMany(ControlCruce::class, 'cruce_id');
    }

    /**
     * @return HasOne<ControlCruce, $this>
     */
    public function controlActual(): HasOne
    {
        return $this->hasOne(ControlCruce::class, 'cruce_id')->where('es_actual', true);
    }

    /**
     * Con un control actual que no venció: el día `vigente_hasta` todavía vale.
     *
     * @param  Builder<self>  $consulta
     */
    public function scopeConControlVigente(Builder $consulta): void
    {
        $consulta->whereHas('controlActual', fn (Builder $control): Builder => $control
            ->where(fn (Builder $q): Builder => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', Calendario::hoy())));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'incompatibilidad_id' => 'integer',
            'desde' => 'immutable_date',
            'hasta' => 'immutable_date',
            'aprobado_por_user_id' => 'integer',
        ];
    }
}
