<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\TipoDocumentoFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M5 · Tipo de comprobante o documento (catálogo 01 de SUNAT). El signo dice si suma o resta en la cuenta corriente.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property bool $es_electronico
 * @property bool $afecta_cuenta_corriente
 * @property int $signo
 * @property bool $es_activo
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(TipoDocumentoFactory::class)]
class TipoDocumento extends Model implements DeclaraCamposSensibles, ElementoCatalogo
{
    /** @use HasFactory<TipoDocumentoFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'tipos_documento';

    protected $fillable = ['codigo', 'nombre', 'es_electronico', 'afecta_cuenta_corriente', 'signo', 'es_activo'];

    public function camposSensibles(): array
    {
        return [];
    }

    /** Plan 1.4, D6. */
    public function camposNoSensibles(): array
    {
        return ['afecta_cuenta_corriente' => 'Marca si el tipo de comprobante mueve la cuenta corriente: no es un número de cuenta.'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_electronico' => 'boolean',
            'afecta_cuenta_corriente' => 'boolean',
            'signo' => 'integer',
            'es_activo' => 'boolean',
        ];
    }
}
