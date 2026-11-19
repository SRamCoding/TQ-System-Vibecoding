<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Models\Concerns\CodigoInmutable;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Illuminate\Database\Eloquent\Model;

/**
 * M3 · Un estado de un tipo de documento (plan 1.5, D1). Lo escribe el sincronizador desde la definición del
 * módulo; por pantalla solo se cambian nombre y color. Lo que sale de la definición se desactiva, nunca se borra.
 *
 * @property int $id
 * @property string $documento_type
 * @property string $codigo
 * @property string $nombre
 * @property TonoEstado $color
 * @property int $orden
 * @property bool $es_inicial
 * @property bool $es_final
 * @property bool $es_editable
 * @property bool $es_cancelacion
 * @property bool $es_activo
 * @property int $version
 */
class Estado extends Model
{
    use CodigoInmutable, ConAuditoria, RegistraCambios;

    protected $table = 'estados';

    protected $fillable = [
        'documento_type', 'codigo', 'nombre', 'color', 'orden',
        'es_inicial', 'es_final', 'es_editable', 'es_cancelacion', 'es_activo',
    ];

    /**
     * @return list<string>
     */
    public function columnasInmutables(): array
    {
        return ['documento_type', 'codigo'];
    }

    /** El color de Filament con que se pinta: «en curso» con el color del área de su documento (plan 1.4ab, D4). */
    public function colorEnPantalla(): string
    {
        $clave = app(RegistroDocumentos::class)->definicionDe($this->documento_type)?->clave();

        return $clave === null ? $this->color->colorFilament() : $this->color->colorDeDocumento($clave);
    }

    /**
     * `version` sube en cada cambio: anotarla en la bitácora solo agrega ruido.
     *
     * @return list<string>
     */
    public function camposSinBitacora(): array
    {
        return ['version'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => TonoEstado::class,
            'orden' => 'integer',
            'es_inicial' => 'boolean',
            'es_final' => 'boolean',
            'es_editable' => 'boolean',
            'es_cancelacion' => 'boolean',
            'es_activo' => 'boolean',
            'version' => 'integer',
        ];
    }
}
