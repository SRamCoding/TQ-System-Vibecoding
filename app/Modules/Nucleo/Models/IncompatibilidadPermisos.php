<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\IncompatibilidadPermisosFactory;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Permission;

/**
 * M10 · Dos permisos que una misma persona no debería juntar, con su motivo (plan 1.4, D1): pedir y aprobar,
 * registrar y aprobar un pago. Ordenados (`permiso_a_id < permiso_b_id`). Con cruces, no se borra: se desactiva.
 *
 * @property int $id
 * @property int $permiso_a_id
 * @property int $permiso_b_id
 * @property string $motivo
 * @property bool $es_activa
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 * @property-read Permission $permisoA
 * @property-read Permission $permisoB
 */
#[UseFactory(IncompatibilidadPermisosFactory::class)]
class IncompatibilidadPermisos extends Model implements DescribibleEnBitacora
{
    /** @use HasFactory<IncompatibilidadPermisosFactory> */
    use ConAuditoria, DescribeBitacora, HasFactory, RegistraCambios;

    protected $table = 'incompatibilidades_permisos';

    protected $fillable = ['permiso_a_id', 'permiso_b_id', 'motivo', 'es_activa'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['es_activa' => true];

    /**
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return ['version'];
    }

    public function etiquetasBitacora(): array
    {
        return ['permiso_a_id' => 'Permiso', 'permiso_b_id' => 'Incompatible con', 'motivo' => 'Motivo', 'es_activa' => 'Activa'];
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return match ($campo) {
            'permiso_a_id', 'permiso_b_id' => Permission::query()->whereKey($valor)->value('name') ?? self::valorComun($valor),
            default => self::valorComun($valor),
        };
    }

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permisoA(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permiso_a_id');
    }

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function permisoB(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permiso_b_id');
    }

    /**
     * @return HasMany<CruceFunciones, $this>
     */
    public function cruces(): HasMany
    {
        return $this->hasMany(CruceFunciones::class, 'incompatibilidad_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permiso_a_id' => 'integer',
            'permiso_b_id' => 'integer',
            'es_activa' => 'boolean',
            'version' => 'integer',
        ];
    }
}
