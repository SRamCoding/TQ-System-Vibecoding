<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\TipoDocumentoIdentidadFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M1 · Tipo de documento de identidad (catálogo 06 de SUNAT): DNI, RUC, carné de extranjería, pasaporte.
 * DNI y RUC los usa el código y tienen reglas fijas (plan 1.3, D3 y D4).
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property ?int $longitud
 * @property bool $es_activo
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(TipoDocumentoIdentidadFactory::class)]
class TipoDocumentoIdentidad extends Model implements ElementoCatalogo
{
    /** @use HasFactory<TipoDocumentoIdentidadFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'tipos_documento_identidad';

    protected $fillable = ['codigo', 'nombre', 'longitud', 'es_activo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'longitud' => 'integer',
            'es_activo' => 'boolean',
        ];
    }
}
