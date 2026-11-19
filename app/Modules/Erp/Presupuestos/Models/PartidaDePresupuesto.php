<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Models;

use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\ParteDeDocumento;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Seguridad\DeclaraCamposSensibles;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una partida del presupuesto con su metrado (plan 2.9). Con APU, su P.U. sale de él; sin APU, se escribe (D9). Parcial
 * = metrado × P.U., redondeado a 2 (D5). El rendimiento (con su unidad) lo usan las líneas de mano de obra y equipo
 * que no llevan uno propio (D12). No confundir con `PresupuestadoDePartida`, el monto de una partida de control.
 *
 * @property int $id
 * @property int $presupuesto_id
 * @property int $titulo_id
 * @property string $codigo
 * @property string $descripcion
 * @property int $unidad_medida_id
 * @property string $metrado
 * @property string $costo_unitario
 * @property string $parcial
 * @property ?string $rendimiento
 * @property ?string $unidad_rendimiento
 * @property int $orden
 * @property int $version
 * @property-read Presupuesto $presupuesto
 * @property-read UnidadMedida $unidadMedida
 */
class PartidaDePresupuesto extends Model implements DeclaraCamposSensibles, DescribibleEnBitacora
{
    use ConAuditoria, DescribeBitacora, ParteDeDocumento, RegistraCambios;

    protected $table = 'presupuesto_partidas';

    protected $fillable = ['presupuesto_id', 'titulo_id', 'codigo', 'descripcion', 'unidad_medida_id', 'metrado', 'costo_unitario', 'parcial', 'rendimiento', 'unidad_rendimiento', 'orden'];

    protected function casts(): array
    {
        return ['metrado' => 'decimal:3', 'costo_unitario' => 'decimal:4', 'parcial' => 'decimal:2', 'rendimiento' => 'decimal:4', 'orden' => 'integer', 'version' => 'integer'];
    }

    public function documentoConEstados(): Model&DocumentoConEstados
    {
        return $this->presupuesto;
    }

    public function camposSensibles(): array
    {
        return ['costo_unitario' => GrupoSensible::Costos, 'parcial' => GrupoSensible::Costos];
    }

    public function camposNoSensibles(): array
    {
        return [];
    }

    public function camposSinBitacora(): array
    {
        return [];
    }

    /** @return BelongsTo<Presupuesto, $this> */
    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class, 'presupuesto_id');
    }

    /** @return BelongsTo<UnidadMedida, $this> */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    /** @return HasMany<LineaApu, $this> */
    public function lineasApu(): HasMany
    {
        return $this->hasMany(LineaApu::class, 'presupuesto_partida_id');
    }

    /** Plan 2.10: los nombres que muestra la pestaña «Historial» de la ficha del presupuesto. */
    public function etiquetasBitacora(): array
    {
        return [
            'codigo' => 'Código', 'descripcion' => 'Descripción', 'titulo_id' => 'Título', 'unidad_medida_id' => 'Unidad',
            'metrado' => 'Metrado', 'costo_unitario' => 'P.U.', 'parcial' => 'Parcial', 'rendimiento' => 'Rendimiento',
            'unidad_rendimiento' => 'Rendimiento por', 'orden' => 'Orden',
        ];
    }

    public function sujetoBitacora(): string
    {
        return 'Partida '.$this->codigo;
    }

    public function valorLegibleBitacora(string $campo, mixed $valor): string
    {
        return $campo === 'unidad_medida_id' && $valor !== null
            ? (UnidadMedida::query()->whereKey($valor)->value('codigo') ?? self::valorComun($valor))
            : self::valorComun($valor);
    }
}
