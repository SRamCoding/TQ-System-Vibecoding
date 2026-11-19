<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\PermisoPorAmbitoFactory;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Permission\Models\Role;

/**
 * M10 · Un rol dado a un usuario solo dentro de un ámbito (una unidad organizativa; luego proyecto, contrato o
 * almacén), con vigencia (plan 1.4, D7). `hasta` es el último día vigente en Lima; vacíos, sin límite.
 *
 * @property int $id
 * @property int $user_id
 * @property int $rol_id
 * @property string $ambito_type
 * @property int $ambito_id
 * @property ?CarbonImmutable $desde
 * @property ?CarbonImmutable $hasta
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 * @property-read User $usuario
 * @property-read Role $rol
 * @property-read Model $ambito
 */
#[UseFactory(PermisoPorAmbitoFactory::class)]
class PermisoPorAmbito extends Model implements DescribibleEnBitacora
{
    /** @use HasFactory<PermisoPorAmbitoFactory> */
    use ConAuditoria, DescribeBitacora, HasFactory, RegistraCambios;

    protected $table = 'permisos_por_ambito';

    protected $fillable = ['user_id', 'rol_id', 'ambito_type', 'ambito_id', 'desde', 'hasta'];

    /**
     * Vigentes en el día (`Y-m-d`): el día `hasta` todavía cuenta, como en la restricción de la base
     * (`daterange(desde, hasta, '[]')`).
     *
     * @param  Builder<static>  $consulta
     */
    public function scopeVigenteEl(Builder $consulta, string $dia): void
    {
        $consulta->where(fn (Builder $q): Builder => $q->whereNull('desde')->orWhere('desde', '<=', $dia))
            ->where(fn (Builder $q): Builder => $q->whereNull('hasta')->orWhere('hasta', '>=', $dia));
    }

    /**
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return ['version'];
    }

    public function etiquetasBitacora(): array
    {
        return [
            'user_id' => 'Usuario', 'rol_id' => 'Rol', 'ambito_type' => 'Tipo de ámbito', 'ambito_id' => 'Ámbito',
            'desde' => 'Desde', 'hasta' => 'Hasta',
        ];
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return match ($campo) {
            'desde' => $valor === null ? 'sin inicio' : self::fechaLegible($valor),
            'hasta' => $valor === null ? 'sin fin' : self::fechaLegible($valor),
            'user_id' => User::query()->whereKey($valor)->value('name') ?? self::valorComun($valor),
            'rol_id' => Role::query()->whereKey($valor)->value('name') ?? self::valorComun($valor),
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
     * @return BelongsTo<Role, $this>
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'rol_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function ambito(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'rol_id' => 'integer',
            'ambito_id' => 'integer',
            'desde' => 'immutable_date',
            'hasta' => 'immutable_date',
            'version' => 'integer',
        ];
    }
}
