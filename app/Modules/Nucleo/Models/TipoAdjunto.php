<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\TipoAdjuntoFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M7 · Un tipo de adjunto (plan 1.8, D4): «Cotización», «Orden firmada», «Examen médico». Puede valer solo para un
 * tipo de registro, pedir menos tamaño o menos extensiones que los parámetros, ser obligatorio y ser sensible.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property ?string $documento_type
 * @property ?string $extensiones
 * @property ?int $tamano_maximo_mb
 * @property bool $es_obligatorio
 * @property ?GrupoSensible $grupo_sensible
 * @property bool $es_activo
 * @property int $version
 */
#[UseFactory(TipoAdjuntoFactory::class)]
class TipoAdjunto extends Model implements ElementoCatalogo
{
    /** @use HasFactory<TipoAdjuntoFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'tipos_adjunto';

    protected $fillable = ['codigo', 'nombre', 'documento_type', 'extensiones', 'tamano_maximo_mb', 'es_obligatorio', 'grupo_sensible', 'es_activo'];

    protected function casts(): array
    {
        return [
            'tamano_maximo_mb' => 'integer',
            'es_obligatorio' => 'boolean',
            'grupo_sensible' => GrupoSensible::class,
            'es_activo' => 'boolean',
        ];
    }
}
