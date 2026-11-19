<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\FeriadoFactory;
use App\Modules\Nucleo\Enums\TipoFeriado;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Un feriado o día no laborable (plan 1.4d, D2). El código es la fecha (AAAA-MM-DD). Lo lee CalendarioLaboral.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property TipoFeriado $tipo
 * @property bool $techquk_no_trabaja
 * @property bool $es_activo
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(FeriadoFactory::class)]
class Feriado extends Model implements ElementoCatalogo
{
    /** @use HasFactory<FeriadoFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'feriados';

    protected $fillable = ['codigo', 'nombre', 'tipo', 'techquk_no_trabaja', 'es_activo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoFeriado::class,
            'techquk_no_trabaja' => 'boolean',
            'es_activo' => 'boolean',
        ];
    }
}
