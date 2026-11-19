<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\ControlCruceFactory;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M10 · El control compensatorio de un cruce de funciones (plan 1.4, D9): qué se revisa y quién responde. Copia la
 * persona del cruce para que la base impida que sea su propio responsable. Renovarlo deja este como no actual.
 *
 * @property int $id
 * @property int $cruce_id
 * @property int $user_id
 * @property string $control
 * @property int $responsable_user_id
 * @property ?CarbonImmutable $vigente_hasta
 * @property bool $es_actual
 * @property int $created_by
 * @property ?int $updated_by
 * @property-read CruceFunciones $cruce
 * @property-read User $responsable
 */
#[UseFactory(ControlCruceFactory::class)]
class ControlCruce extends Model implements DescribibleEnBitacora
{
    /** @use HasFactory<ControlCruceFactory> */
    use ConAuditoria, DescribeBitacora, HasFactory, RegistraCambios;

    protected $table = 'controles_cruce';

    protected $fillable = ['cruce_id', 'user_id', 'control', 'responsable_user_id', 'vigente_hasta', 'es_actual'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['es_actual' => true];

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
            'cruce_id' => 'Cruce', 'user_id' => 'Usuario', 'control' => 'Control', 'responsable_user_id' => 'Responsable',
            'vigente_hasta' => 'Vigente hasta', 'es_actual' => 'Actual',
        ];
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return match ($campo) {
            'vigente_hasta' => $valor === null ? 'sin vencimiento' : self::fechaLegible($valor),
            'user_id', 'responsable_user_id' => User::query()->whereKey($valor)->value('name') ?? self::valorComun($valor),
            default => self::valorComun($valor),
        };
    }

    /**
     * @return BelongsTo<CruceFunciones, $this>
     */
    public function cruce(): BelongsTo
    {
        return $this->belongsTo(CruceFunciones::class, 'cruce_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cruce_id' => 'integer',
            'user_id' => 'integer',
            'responsable_user_id' => 'integer',
            'vigente_hasta' => 'immutable_date',
            'es_actual' => 'boolean',
        ];
    }
}
