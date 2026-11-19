<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\PlantillaFactory;
use App\Modules\Nucleo\Models\Concerns\CodigoInmutable;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Una plantilla personalizable (ADR 0022; plan 2.11): de un tipo que registra su módulo, personal (con su dueño) o de
 * la empresa (D1). Su contenido vive en sus versiones; la vigente es la de número mayor (D3). Se desactiva, no se borra.
 * El tipo, el alcance y el dueño no cambian: publicar una personal crea otra, de la empresa.
 *
 * @property int $id
 * @property string $tipo
 * @property string $nombre
 * @property string $nombre_comparable
 * @property ?string $descripcion
 * @property string $alcance
 * @property ?int $propietario_user_id
 * @property bool $es_activa
 * @property int $version
 */
#[UseFactory(PlantillaFactory::class)]
class Plantilla extends Model
{
    /** @use HasFactory<PlantillaFactory> */
    use CodigoInmutable, ConAuditoria, HasFactory, RegistraCambios;

    public const string PERSONAL = 'personal';

    public const string EMPRESA = 'empresa';

    protected $table = 'plantillas';

    protected $fillable = ['tipo', 'nombre', 'descripcion', 'alcance', 'propietario_user_id', 'es_activa'];

    protected static function booted(): void
    {
        static::saving(function (Plantilla $plantilla): void {
            if ($plantilla->isDirty('nombre') || ! $plantilla->exists) {
                $plantilla->nombre_comparable = Motivo::comparable($plantilla->nombre);
            }
        });
        static::deleting(fn (): never => throw new LogicException('Una plantilla no se borra: se desactiva (ADR 0022).'));
    }

    /**
     * @return list<string>
     */
    public function columnasInmutables(): array
    {
        return ['tipo', 'alcance', 'propietario_user_id'];
    }

    /**
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return ['version', 'nombre_comparable'];
    }

    public function esDeLaEmpresa(): bool
    {
        return $this->alcance === self::EMPRESA;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function propietario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'propietario_user_id');
    }

    /**
     * @return HasMany<PlantillaVersion, $this>
     */
    public function versiones(): HasMany
    {
        return $this->hasMany(PlantillaVersion::class);
    }

    /**
     * La que se aplica (D3): la de número mayor.
     *
     * @return HasOne<PlantillaVersion, $this>
     */
    public function vigente(): HasOne
    {
        return $this->hasOne(PlantillaVersion::class)->ofMany('numero', 'max');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['es_activa' => 'boolean', 'version' => 'integer'];
    }
}
