<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services\EnMemoria;

use App\Modules\Erp\Presupuestos\Excepciones\PresupuestoRechazado;
use App\Modules\Erp\Presupuestos\Services\PresupuestoNoEncontrado;
use App\Modules\Erp\Presupuestos\Services\PresupuestosService;
use App\Modules\Erp\Presupuestos\Services\ResumenPresupuesto;
use App\Modules\Nucleo\Models\User;

/**
 * C8 en memoria (plan 2.9, D7), para quien prueba su módulo (Comercial: la cotización) sin armar un presupuesto de
 * verdad: `$this->app->instance(PresupuestosService::class, $falsa = new PresupuestosEnMemoria)` y luego
 * `$falsa->agregar(...)` con el resumen que la prueba necesite. Pasa las mismas pruebas del contrato que
 * `PresupuestosEnBase` (`tests/Modules/Erp/Presupuestos/ContratoPresupuestosTest.php`). No calcula: guarda lo que le dan;
 * `ofertar()` cambia el estado como la real (con `agregar(..., faltas: [...])`, falla como un presupuesto incompleto).
 */
final class PresupuestosEnMemoria implements PresupuestosService
{
    /** @var array<int, ResumenPresupuesto> */
    private array $presupuestos = [];

    /** @var array<int, list<string>> lo que le falta a cada uno para ofertarse */
    private array $faltas = [];

    /** @var list<int> los que se ofertaron por aquí, en orden (para que la prueba de Comercial lo revise) */
    public array $ofertados = [];

    /**
     * @param  list<string>  $faltas  lo que le falta, como lo diría la real; vacío: completo
     */
    public function agregar(ResumenPresupuesto $resumen, array $faltas = []): ResumenPresupuesto
    {
        $this->faltas[$resumen->id] = $faltas;

        return $this->presupuestos[$resumen->id] = $resumen;
    }

    public function resumen(int $presupuestoId): ResumenPresupuesto
    {
        return $this->presupuestos[$presupuestoId] ?? throw PresupuestoNoEncontrado::id($presupuestoId);
    }

    public function deOportunidad(int $oportunidadId): array
    {
        $lista = array_values(array_filter(
            $this->presupuestos,
            fn (ResumenPresupuesto $r): bool => $r->oportunidadId === $oportunidadId && in_array($r->estado, ['borrador', 'ofertado'], true),
        ));
        usort($lista, fn (ResumenPresupuesto $a, ResumenPresupuesto $b): int => [$a->numero, $b->numeroVersion] <=> [$b->numero, $a->numeroVersion]);

        return $lista;
    }

    public function ofertar(int $presupuestoId, User $usuario): ResumenPresupuesto
    {
        $resumen = $this->resumen($presupuestoId);

        if (in_array($resumen->estado, ['ofertado', 'aprobado'], true)) {
            return $resumen;
        }

        if ($resumen->estado !== 'borrador') {
            throw PresupuestoRechazado::conMotivo("El presupuesto {$resumen->numero} v{$resumen->numeroVersion} está ".mb_strtolower($resumen->estadoNombre).': no se oferta.');
        }

        if ($this->faltas[$presupuestoId] !== []) {
            throw PresupuestoRechazado::conMotivo('El presupuesto está incompleto: '.implode('; ', $this->faltas[$presupuestoId]).'.');
        }

        foreach ($this->presupuestos as $id => $otro) {
            if ($otro->numero === $resumen->numero && $otro->numeroVersion < $resumen->numeroVersion && $otro->estado === 'ofertado') {
                $this->presupuestos[$id] = self::conEstado($otro, 'reemplazado', 'Reemplazado');
            }
        }

        $this->ofertados[] = $presupuestoId;

        return $this->presupuestos[$presupuestoId] = self::conEstado($resumen, 'ofertado', 'Ofertado');
    }

    private static function conEstado(ResumenPresupuesto $r, string $estado, string $nombre): ResumenPresupuesto
    {
        return new ResumenPresupuesto(
            $r->id, $r->numero, $r->numeroVersion, $r->nombre, $estado, $nombre, $r->esVigente, $r->proyectoId,
            $r->oportunidadId, $r->monedaCodigo, $r->usaApu, $r->gastosGeneralesPct, $r->utilidadPct, $r->igvPct,
            $r->costoDirecto, $r->gastosGenerales, $r->utilidad, $r->igv, $r->total, $r->sinDetallar, $r->partidas,
        );
    }
}
