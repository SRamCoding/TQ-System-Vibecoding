<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\RubroFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M5 · Rubro de una empresa: una sección de la CIIU Rev. 4, la que SUNAT usa en el RUC (plan 2.20a, N3). Catálogo cerrado.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property bool $es_activo
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(RubroFactory::class)]
class Rubro extends Model implements ElementoCatalogo
{
    /** @use HasFactory<RubroFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'rubros';

    protected $fillable = ['codigo', 'nombre', 'es_activo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_activo' => 'boolean',
        ];
    }
}
