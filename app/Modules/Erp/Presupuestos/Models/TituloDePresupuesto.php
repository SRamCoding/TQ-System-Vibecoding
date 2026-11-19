<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Models;

use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Estados\ParteDeDocumento;
use App\Modules\Nucleo\Historial\DescribeBitacora;
use App\Modules\Nucleo\Historial\DescribibleEnBitacora;
use App\Modules\Nucleo\Models\Concerns\ConAuditoria;
use App\Modules\Nucleo\Models\Concerns\RegistraCambios;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un título del presupuesto (01 CÁMARAS, 02 CABLEADO Y POSTES). Agrupa partidas; su monto es la suma de las de debajo.
 *
 * @property int $id
 * @property int $presupuesto_id
 * @property ?int $padre_id
 * @property string $codigo
 * @property string $nombre
 * @property int $orden
 * @property int $version
 * @property-read Presupuesto $presupuesto
 */
class TituloDePresupuesto extends Model implements DescribibleEnBitacora
{
    use ConAuditoria, DescribeBitacora, ParteDeDocumento, RegistraCambios;

    protected $table = 'presupuesto_titulos';

    protected $fillable = ['presupuesto_id', 'padre_id', 'codigo', 'nombre', 'orden'];

    protected function casts(): array
    {
        return ['orden' => 'integer', 'version' => 'integer'];
    }

    public function documentoConEstados(): Model&DocumentoConEstados
    {
        return $this->presupuesto;
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

    /** Plan 2.10: los nombres que muestra la pestaña «Historial» de la ficha del presupuesto. */
    public function etiquetasBitacora(): array
    {
        return ['codigo' => 'Código', 'nombre' => 'Nombre', 'padre_id' => 'Título padre', 'orden' => 'Orden'];
    }

    public function sujetoBitacora(): string
    {
        return 'Título '.$this->codigo;
    }
}
