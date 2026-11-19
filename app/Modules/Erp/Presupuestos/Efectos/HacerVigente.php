<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Efectos;

use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Excepciones\PresupuestoRechazado;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Proyectos\Services\ProyectoRechazado;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Estados\Efecto;
use App\Modules\Nucleo\Services\TransicionesService;
use Illuminate\Database\Eloquent\Model;

/**
 * Al traer el presupuesto al proyecto (plan 2.9, D6): lo enlaza al proyecto (por C2, que acepte costos) y lo deja
 * vigente; la versión anterior del mismo presupuesto pasa a reemplazada. Un proyecto con otro presupuesto vigente no
 * recibe uno distinto: los cambios van como versión nueva del vigente. Un aprobado no se anula: nada que revertir.
 */
final readonly class HacerVigente implements Efecto
{
    public function __construct(private PedidoTraerAlProyecto $pedido) {}

    public function aplicar(Model $documento): array
    {
        if (! $documento instanceof Presupuesto) {
            return [];
        }

        $proyectoId = $documento->proyecto_id ?? $this->pedido->proyectoId();

        try {
            $proyecto = app(ProyectosService::class)->resumen($proyectoId);
        } catch (ProyectoRechazado) {
            throw PresupuestoRechazado::conMotivo('Ese proyecto no existe.');
        }

        if (! $proyecto->aceptaCostos) {
            throw PresupuestoRechazado::conMotivo("El proyecto {$proyecto->codigo} está ".mb_strtolower($proyecto->estadoNombre).': no recibe presupuesto.');
        }

        $anterior = Presupuesto::query()->where('proyecto_id', $proyectoId)->where('es_vigente', true)->whereKeyNot($documento->id)->lockForUpdate()->first();

        if ($anterior !== null) {
            if ($anterior->numero !== $documento->numero) {
                throw PresupuestoRechazado::conMotivo("El proyecto {$proyecto->codigo} ya tiene vigente el presupuesto {$anterior->numero}: los cambios van como versión nueva de ese.");
            }

            app(TransicionesService::class)->transicionarComoSistema($anterior, DefinicionPresupuesto::REEMPLAZAR);
        }

        $documento->proyecto_id = $proyectoId;
        $documento->es_vigente = true;
        $documento->save();

        return ['proyecto_id' => $proyectoId, 'reemplazado_id' => $anterior?->id];
    }

    public function revertir(Model $documento, array $datos): void
    {
        // Un presupuesto aprobado no se anula (D6): solo lo reemplaza una versión nueva.
    }
}
