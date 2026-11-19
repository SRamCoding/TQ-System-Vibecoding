<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Models;

use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\ParteDeDocumento;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El presupuestado de una hoja en una versión del árbol (plan 2.1, D5). Solo las hojas tienen monto; un padre suma. Es
 * parte del documento del árbol: en un árbol aprobado no se agrega, no se cambia ni se borra (lo rechaza el modelo).
 *
 * @property int $id
 * @property int $proyecto_id
 * @property int $partida_id
 * @property int $arbol_id
 * @property string $monto
 * @property-read ArbolPartidas $arbol
 */
class PresupuestadoDePartida extends Model implements DeclaraCamposSensibles
{
    use ConAuditoria, ParteDeDocumento, RegistraCambios;

    protected $table = 'partida_presupuestos';

    protected $fillable = ['proyecto_id', 'partida_id', 'arbol_id', 'monto'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2'];
    }

    public function documentoConEstados(): Model&DocumentoConEstados
    {
        return $this->arbol;
    }

    /** @return BelongsTo<ArbolPartidas, $this> */
    public function arbol(): BelongsTo
    {
        return $this->belongsTo(ArbolPartidas::class, 'arbol_id');
    }

    public function camposSensibles(): array
    {
        return ['monto' => GrupoSensible::Costos];
    }

    public function camposNoSensibles(): array
    {
        return [];
    }

    public function camposSinBitacora(): array
    {
        return [];
    }
}
