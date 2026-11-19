<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Models;

use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\TieneEstados;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una versión del árbol de partidas de control de un proyecto (plan 2.1, D5). Al aprobarse crea la PC-99, pasa el
 * proyecto a «en ejecución» y habilita imputar a sus partidas.
 *
 * @property int $id
 * @property int $proyecto_id
 * @property int $numero_version
 * @property int $estado_id
 * @property string $costo_presupuestado
 * @property int $version
 * @property ?int $presupuesto_id
 * @property ?int $plantilla_version_id
 * @property-read Estado $estado
 */
class ArbolPartidas extends Model implements DeclaraCamposSensibles, DocumentoConEstados
{
    use ConAuditoria, RegistraCambios, TieneEstados;

    protected $table = 'partida_arboles';

    protected $fillable = ['proyecto_id', 'numero_version', 'costo_presupuestado', 'presupuesto_id', 'plantilla_version_id'];

    protected function casts(): array
    {
        return ['costo_presupuestado' => 'decimal:2', 'numero_version' => 'integer', 'version' => 'integer'];
    }

    public function camposSensibles(): array
    {
        return ['costo_presupuestado' => GrupoSensible::Costos];
    }

    public function camposNoSensibles(): array
    {
        return [];
    }

    public function camposSinBitacora(): array
    {
        return [];
    }

    /** @return BelongsTo<Estado, $this> */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    /** @return HasMany<PartidaControl, $this> */
    public function partidas(): HasMany
    {
        return $this->hasMany(PartidaControl::class, 'arbol_alta_id');
    }
}
