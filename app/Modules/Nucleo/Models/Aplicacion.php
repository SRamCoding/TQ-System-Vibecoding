<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Adjuntos\ConAdjuntos;
use App\Modules\Nucleo\database\factories\AplicacionFactory;
use App\Modules\Nucleo\Enums\IconoAplicacion;
use App\Modules\Nucleo\Enums\ProgramaEscritorio;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Una aplicación web de Techquk en el panel de aplicaciones (plan 1.4p, D7): Word, Excel, PowerPoint… La pone
 * administración para todos; cada persona puede ocultarla. Lleva adjuntos solo para su ícono subido (plan 1.4z, D9):
 * los ve cualquiera (el ícono se muestra a todos) y los sube quien edita el catálogo.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $enlace
 * @property IconoAplicacion $icono
 * @property int $orden
 * @property bool $es_activo
 * @property int $version
 * @property ?int $icono_adjunto_id
 * @property ?ProgramaEscritorio $escritorio
 */
#[UseFactory(AplicacionFactory::class)]
class Aplicacion extends Model implements ConAdjuntos, ElementoCatalogo
{
    /** @use HasFactory<AplicacionFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'aplicaciones';

    /** Sin `icono_adjunto_id`: no se edita en el catálogo, lo pone IconosAplicacionService al subir el ícono. */
    protected $fillable = ['codigo', 'nombre', 'enlace', 'icono', 'orden', 'es_activo', 'escritorio'];

    protected function casts(): array
    {
        return [
            'icono' => IconoAplicacion::class,
            'escritorio' => ProgramaEscritorio::class,
            'orden' => 'integer',
            'es_activo' => 'boolean',
        ];
    }

    public function puedeVerAdjuntos(User $usuario): bool
    {
        return true;
    }

    public function puedeAdjuntar(User $usuario): bool
    {
        return $usuario->can('catalogos.editar');
    }
}
