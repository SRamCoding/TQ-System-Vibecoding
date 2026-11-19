<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Models\Concerns\CodigoInmutable;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * M3 · Un motivo de una lista (plan 1.5, D9). Se agrega, renombra y desactiva por pantalla; usado no se borra (la
 * llave decide). Cada lista tiene su «Otro», que obliga a escribir el detalle y no se desactiva.
 *
 * @property int $id
 * @property int $tipo_motivo_id
 * @property string $codigo
 * @property string $nombre
 * @property string $nombre_comparable
 * @property bool $es_cambio_cliente
 * @property bool $es_otro
 * @property bool $es_activo
 * @property int $version
 */
class Motivo extends Model
{
    use CodigoInmutable, ConAuditoria, RegistraCambios;

    public const CODIGO_OTRO = 'otro';

    protected $table = 'motivos';

    protected $fillable = ['tipo_motivo_id', 'codigo', 'nombre', 'es_cambio_cliente', 'es_otro', 'es_activo'];

    /**
     * @return list<string>
     */
    public function columnasInmutables(): array
    {
        return ['tipo_motivo_id', 'codigo', 'es_otro'];
    }

    /**
     * `version` sube en cada cambio: anotarla en la bitácora solo agrega ruido.
     *
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return ['version', 'nombre_comparable'];
    }

    protected static function booted(): void
    {
        static::saving(function (Motivo $motivo): void {
            if ($motivo->isDirty('nombre') || ! $motivo->exists) {
                $motivo->nombre_comparable = self::comparable($motivo->nombre);
            }
        });
    }

    /**
     * El nombre como lo compara la base: sin espacios al borde ni repetidos, en minúsculas y sin tildes. Lo calcula
     * la base, con la misma expresión que su CHECK.
     */
    public static function comparable(string $nombre): string
    {
        return (string) DB::selectOne("SELECT regexp_replace(lower(unaccent(btrim(?))), '\\s+', ' ', 'g') AS valor", [$nombre])->valor;
    }

    /**
     * @return BelongsTo<TipoMotivo, $this>
     */
    public function tipoMotivo(): BelongsTo
    {
        return $this->belongsTo(TipoMotivo::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_cambio_cliente' => 'boolean',
            'es_otro' => 'boolean',
            'es_activo' => 'boolean',
            'version' => 'integer',
        ];
    }
}
