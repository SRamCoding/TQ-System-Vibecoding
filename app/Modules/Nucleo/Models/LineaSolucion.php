<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\LineaSolucionFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M5 · Línea del portafolio de Techquk: clasificación común de catálogo, oportunidades y proyectos.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property bool $es_activa
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(LineaSolucionFactory::class)]
class LineaSolucion extends Model implements ElementoCatalogo
{
    /** @use HasFactory<LineaSolucionFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'lineas_solucion';

    protected $fillable = ['codigo', 'nombre', 'es_activa'];

    public static function columnaActivo(): string
    {
        return 'es_activa';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_activa' => 'boolean',
        ];
    }
}
