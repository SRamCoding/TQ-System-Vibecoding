<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\AplicacionPropiaFactory;
use App\Modules\Nucleo\Enums\IconoAplicacion;
use App\Modules\Nucleo\Enums\ProgramaEscritorio;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Una aplicación que una persona sumó a su panel (plan 1.4p, D7). Solo la ve ella. Solo la escribe AplicacionesService.
 *
 * @property int $id
 * @property int $user_id
 * @property string $nombre
 * @property string $enlace
 * @property IconoAplicacion $icono
 * @property ?ProgramaEscritorio $escritorio
 * @property int $version
 */
#[UseFactory(AplicacionPropiaFactory::class)]
class AplicacionPropia extends Model
{
    /** @use HasFactory<AplicacionPropiaFactory> */
    use ConAuditoria, HasFactory;

    protected $table = 'aplicaciones_propias';

    protected $fillable = ['user_id', 'nombre', 'enlace', 'icono', 'escritorio'];

    protected function casts(): array
    {
        return [
            'icono' => IconoAplicacion::class,
            'escritorio' => ProgramaEscritorio::class,
        ];
    }
}
