<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\OrigenComercialFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M5 · Origen comercial: de dónde llega una cuenta, un lead o una oportunidad (plan 2.20a, N3). Común al CRM.
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property bool $es_activo
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(OrigenComercialFactory::class)]
class OrigenComercial extends Model implements ElementoCatalogo
{
    /** @use HasFactory<OrigenComercialFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'origenes_comerciales';

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
