<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\RolPartyFactory;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\ElementoCatalogo;
use App\Modules\Nucleo\Models\Concerns\EsCatalogo;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M1 · Papel que cumple una party: cliente, proveedor, aliado, entidad, colaborador, banco. Un rol nuevo
 * es una fila, no una tabla (plan 1.3, D3).
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property ?string $descripcion
 * @property bool $es_activo
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(RolPartyFactory::class)]
class RolParty extends Model implements ElementoCatalogo
{
    /** @use HasFactory<RolPartyFactory> */
    use ConAuditoria, EsCatalogo, HasFactory;

    protected $table = 'roles_party';

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'es_activo'];

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
