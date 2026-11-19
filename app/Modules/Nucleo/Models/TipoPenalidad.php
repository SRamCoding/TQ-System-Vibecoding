<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\TipoPenalidadFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M5 · Tipo de penalidad contractual: mora, incumplimiento parcial, resolución.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property bool $es_activo
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(TipoPenalidadFactory::class)]
class TipoPenalidad extends Model implements ElementoCatalogo
{
    /** @use HasFactory<TipoPenalidadFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'tipos_penalidad';

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
