<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models;

use App\Modules\Nucleo\database\factories\ParametroFactory;
use App\Modules\Nucleo\Enums\TipoParametro;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * M9 · Un valor del negocio que se cambia sin desplegar (IGV, moneda local, umbrales). Se lee con
 * ParametrosService, nunca con este modelo desde otro módulo. Sensible: bitácora campo por campo.
 *
 * @property int $id
 * @property string $grupo
 * @property string $clave
 * @property string $valor
 * @property TipoParametro $tipo
 * @property string $descripcion
 * @property bool $es_editable
 * @property int $version
 * @property int $created_by
 * @property ?int $updated_by
 */
#[UseFactory(ParametroFactory::class)]
class Parametro extends Model
{
    /** @use HasFactory<ParametroFactory> */
    use ConAuditoria, HasFactory, RegistraCambios;

    protected $table = 'parametros';

    protected $fillable = ['grupo', 'clave', 'valor', 'tipo', 'descripcion', 'es_editable'];

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
            'tipo' => TipoParametro::class,
            'es_editable' => 'boolean',
            'version' => 'integer',
        ];
    }
}
