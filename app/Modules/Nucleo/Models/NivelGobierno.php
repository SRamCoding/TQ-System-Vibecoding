<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\NivelGobiernoFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M5 · Nivel de gobierno de una cuenta pública: local, regional o nacional (plan 2.20a, N3).
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property bool $es_activo
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(NivelGobiernoFactory::class)]
class NivelGobierno extends Model implements ElementoCatalogo
{
    /** @use HasFactory<NivelGobiernoFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'niveles_gobierno';

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
