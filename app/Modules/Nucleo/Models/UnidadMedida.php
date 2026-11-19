<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\UnidadMedidaFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M5 · Unidad de medida con código del catálogo 03 de SUNAT. `codigo_sunat` es el código con que se factura: el mismo
 * código salvo en las propias, como GLB (global), que se factura como ZZ (plan 2.9). Si no se da, es el código.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string $abreviatura
 * @property string $codigo_sunat
 * @property bool $permite_decimales
 * @property bool $es_activa
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(UnidadMedidaFactory::class)]
class UnidadMedida extends Model implements ElementoCatalogo
{
    /** @use HasFactory<UnidadMedidaFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'unidades_medida';

    protected $fillable = ['codigo', 'nombre', 'abreviatura', 'codigo_sunat', 'permite_decimales', 'es_activa'];

    protected static function booted(): void
    {
        static::saving(function (self $unidad): void {
            if (trim((string) $unidad->codigo_sunat) === '') {
                $unidad->codigo_sunat = $unidad->codigo;
            }
        });
    }

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
            'permite_decimales' => 'boolean',
            'es_activa' => 'boolean',
        ];
    }
}
