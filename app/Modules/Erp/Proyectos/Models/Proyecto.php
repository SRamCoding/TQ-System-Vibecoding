<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Models;

use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\TieneEstados;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\CodigoInmutable;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El proyecto (plan 2.1, D2 y D3): la ficha de ejecución contra la que se imputa el costo. Lo mínimo; el área 4 sigue
 * desde aquí. Otros módulos lo leen por `ProyectosService` (C2), nunca por este modelo. El cliente es un `party_id`: su
 * nombre se lee con `PartiesService`. El código no cambia nunca (D3).
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property int $party_id
 * @property bool $es_interno
 * @property ?int $contrato_id
 * @property int $jefe_proyecto_user_id
 * @property CarbonImmutable $fecha_inicio
 * @property CarbonImmutable $fecha_fin_prevista
 * @property int $moneda_id
 * @property int $estado_id
 * @property int $version
 * @property ?string $foto_ruta
 * @property-read Estado $estado
 */
class Proyecto extends Model implements DescribibleEnBitacora, DocumentoConEstados
{
    use CodigoInmutable, ConAuditoria, DescribeBitacora, RegistraCambios, TieneEstados;

    protected $table = 'proyectos';

    protected $fillable = [
        'codigo', 'nombre', 'party_id', 'es_interno', 'contrato_id', 'oportunidad_id', 'jefe_proyecto_user_id',
        'fecha_inicio', 'fecha_fin_prevista', 'moneda_id',
    ];

    protected function casts(): array
    {
        return [
            'es_interno' => 'boolean',
            'contrato_id' => 'integer',
            'fecha_inicio' => 'immutable_date',
            'fecha_fin_prevista' => 'immutable_date',
            'version' => 'integer',
        ];
    }

    /** Plan 2.10, D3: la bitácora que lee la pestaña «Historial» de la ficha. */
    public function camposSinBitacora(): array
    {
        // La foto (plan 2.61): su cambio lo anota la acción registrada; la ruta no le dice nada a nadie.
        return ['version', 'foto_ruta'];
    }

    public function etiquetasBitacora(): array
    {
        return [
            'codigo' => 'Código', 'nombre' => 'Nombre', 'party_id' => 'Cliente', 'es_interno' => 'Interno',
            'contrato_id' => 'Contrato', 'jefe_proyecto_user_id' => 'Jefe de proyecto', 'fecha_inicio' => 'Inicio',
            'fecha_fin_prevista' => 'Fin previsto', 'moneda_id' => 'Moneda', 'estado_id' => 'Estado',
        ];
    }

    public function sujetoBitacora(): string
    {
        return 'Proyecto';
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return match (true) {
            $valor === null => '—',
            $campo === 'party_id' => Party::query()->whereKey($valor)->value('razon_social') ?? self::valorComun($valor),
            $campo === 'jefe_proyecto_user_id' => User::query()->whereKey($valor)->value('name') ?? self::valorComun($valor),
            $campo === 'moneda_id' => Moneda::query()->whereKey($valor)->value('codigo') ?? self::valorComun($valor),
            $campo === 'estado_id' => Estado::query()->whereKey($valor)->value('nombre') ?? self::valorComun($valor),
            in_array($campo, ['fecha_inicio', 'fecha_fin_prevista'], true) => self::fechaLegible($valor),
            default => self::valorComun($valor),
        };
    }

    public function columnasInmutables(): array
    {
        return ['codigo'];
    }

    /** @return BelongsTo<Estado, $this> */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    /** @return BelongsTo<User, $this> */
    public function jefe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jefe_proyecto_user_id');
    }
}
