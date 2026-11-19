<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Models;

use App\Modules\Nucleo\Models\Concerns\CodigoInmutable;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un nodo del árbol de partidas (plan 2.1, D5): contra sus hojas se carga cada costo real. La cambia solo
 * `ArbolPartidasService`, con la fila de su árbol bloqueada; otros módulos la ven por `PartidasService` (C1).
 *
 * @property int $id
 * @property int $proyecto_id
 * @property ?int $padre_id
 * @property int $arbol_alta_id
 * @property string $codigo
 * @property string $nombre
 * @property bool $es_hoja
 * @property int $responsable_user_id
 * @property bool $es_gastos_generales
 * @property bool $es_no_presupuestado
 * @property bool $es_activa
 * @property int $version
 * @property ?int $origen_titulo_id
 * @property ?int $origen_partida_id
 * @property ?int $origen_presupuesto_id
 * @property-read ArbolPartidas $arbol
 */
class PartidaControl extends Model implements DeclaraCamposSensibles
{
    use CodigoInmutable, ConAuditoria, RegistraCambios;

    /** El código de la partida de no presupuestados, que crea el sistema (v4, parte 6). */
    public const string CODIGO_NO_PRESUPUESTADA = 'PC-99';

    protected $table = 'partidas_control';

    protected $fillable = [
        'proyecto_id', 'padre_id', 'arbol_alta_id', 'codigo', 'nombre', 'es_hoja', 'responsable_user_id',
        'es_gastos_generales', 'es_no_presupuestado', 'es_activa', 'origen_titulo_id', 'origen_partida_id', 'origen_presupuesto_id',
    ];

    /**
     * El origen es histórico (plan 2.9b, D6): de qué título o partida del presupuesto salió. Renombrar o cambiar el
     * responsable no lo toca, y una partida no cambia de origen.
     *
     * @return list<string>
     */
    public function columnasInmutables(): array
    {
        return ['origen_titulo_id', 'origen_partida_id', 'origen_presupuesto_id'];
    }

    protected function casts(): array
    {
        return [
            'es_hoja' => 'boolean',
            'es_gastos_generales' => 'boolean',
            'es_no_presupuestado' => 'boolean',
            'es_activa' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function camposSensibles(): array
    {
        return [];
    }

    public function camposNoSensibles(): array
    {
        return [];
    }

    public function camposSinBitacora(): array
    {
        return [];
    }

    /** @return BelongsTo<ArbolPartidas, $this> */
    public function arbol(): BelongsTo
    {
        return $this->belongsTo(ArbolPartidas::class, 'arbol_alta_id');
    }
}
