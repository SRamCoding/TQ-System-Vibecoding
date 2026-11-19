<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\PuestoFactory;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * M10 · El cargo de una persona en el organigrama. No da permisos: eso lo hacen los roles (v4, glosario). La unidad
 * de un usuario sale de su puesto.
 *
 * @property int $id
 * @property string $nombre
 * @property int $unidad_organizativa_id
 * @property bool $es_activo
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 * @property-read UnidadOrganizativa $unidad
 */
#[UseFactory(PuestoFactory::class)]
class Puesto extends Model implements DescribibleEnBitacora
{
    /** @use HasFactory<PuestoFactory> */
    use ConAuditoria, DescribeBitacora, HasFactory, RegistraCambios;

    protected $table = 'puestos';

    protected $fillable = ['nombre', 'unidad_organizativa_id', 'es_activo'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['es_activo' => true];

    /**
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return ['version'];
    }

    public function etiquetasBitacora(): array
    {
        return ['nombre' => 'Nombre', 'unidad_organizativa_id' => 'Unidad', 'es_activo' => 'Activo'];
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return match ($campo) {
            'unidad_organizativa_id' => UnidadOrganizativa::query()->whereKey($valor)->value('nombre') ?? self::valorComun($valor),
            default => self::valorComun($valor),
        };
    }

    /**
     * @return BelongsTo<UnidadOrganizativa, $this>
     */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadOrganizativa::class, 'unidad_organizativa_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function ocupantes(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unidad_organizativa_id' => 'integer',
            'es_activo' => 'boolean',
            'version' => 'integer',
        ];
    }
}
