<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\MonedaFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M5 · Moneda (ISO 4217). Soles y dólares.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $simbolo
 * @property int $decimales
 * @property bool $es_activa
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(MonedaFactory::class)]
class Moneda extends Model implements ElementoCatalogo
{
    /** @use HasFactory<MonedaFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'monedas';

    protected $fillable = ['codigo', 'nombre', 'simbolo', 'decimales', 'es_activa'];

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
            'decimales' => 'integer',
            'es_activa' => 'boolean',
        ];
    }
}
