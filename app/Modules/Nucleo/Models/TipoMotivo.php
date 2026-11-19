<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\CodigoInmutable;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * M3 · Una lista de motivos: la de una transición que exige motivo (código = su clave, `compras.orden.anular`) o la
 * que declara un módulo (`perdida_oportunidad`). Las declara el código; no se crean por pantalla (plan 1.5, D9).
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 */
class TipoMotivo extends Model
{
    use CodigoInmutable, ConAuditoria;

    protected $table = 'tipos_motivo';

    protected $fillable = ['codigo', 'nombre'];

    /**
     * @return list<string>
     */
    public function columnasInmutables(): array
    {
        return ['codigo'];
    }

    /**
     * @return HasMany<Motivo, $this>
     */
    public function motivos(): HasMany
    {
        return $this->hasMany(Motivo::class);
    }
}
