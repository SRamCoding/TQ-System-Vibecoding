<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\AlertaTipoFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * M13 · Lo editable de un tipo de alerta (plan 1.10, D1): si está activa, su anticipación, sus textos (nulos = los de
 * fábrica) y, en `alerta_tipo_roles`, a quién le llega. El resto lo declara su módulo (`DefinicionAlerta`). Lo
 * escribe solo `AlertasService`.
 *
 * @property int $id
 * @property string $codigo
 * @property bool $es_activa
 * @property ?string $anticipacion_dias
 * @property ?string $asunto
 * @property ?string $texto
 * @property int $version
 */
#[UseFactory(AlertaTipoFactory::class)]
class AlertaTipo extends Model
{
    /** @use HasFactory<AlertaTipoFactory> */
    use ConAuditoria, HasFactory;

    protected $table = 'alerta_tipos';

    protected $fillable = ['codigo', 'es_activa', 'anticipacion_dias', 'asunto', 'texto'];

    protected function casts(): array
    {
        return ['es_activa' => 'boolean', 'version' => 'integer'];
    }

    /**
     * @return HasMany<AlertaTipoRol, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(AlertaTipoRol::class);
    }
}
