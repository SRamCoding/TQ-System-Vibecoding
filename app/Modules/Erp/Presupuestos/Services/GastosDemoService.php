<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Imputacion\DestinoPartida;
use App\Modules\Erp\Presupuestos\Models\GastoDemo;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Imputacion\MomentoImputacion;
use App\Modules\Nucleo\Services\ImputacionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * MVP rápido (ficha t0-datos-demo, MV-003): registra un gasto de demostración en una partida de control (o en la PC-99,
 * con su motivo) y mueve sus tres montos por el mismo `ImputacionService` que usará una orden de compra. Así el
 * tablero del proyecto lo suma con el cálculo de siempre. Solo lo usan los datos de demostración.
 */
final readonly class GastosDemoService
{
    public function registrar(
        int $partidaId,
        string $descripcion,
        string $proveedor,
        CarbonImmutable $fecha,
        string $comprometido,
        string $ejecutado,
        string $pagado,
        ?int $motivoId = null,
        ?string $comentario = null,
    ): GastoDemo {
        return DB::transaction(function () use ($partidaId, $descripcion, $proveedor, $fecha, $comprometido, $ejecutado, $pagado, $motivoId, $comentario): GastoDemo {
            $partida = PartidaControl::query()->findOrFail($partidaId);
            $gasto = GastoDemo::query()->create([
                'numero' => sprintf('GD-%s-%04d', $fecha->format('Y'), (int) GastoDemo::query()->max('id') + 1),
                'proyecto_id' => $partida->proyecto_id,
                'partida_control_id' => $partida->id,
                'descripcion' => $descripcion,
                'proveedor' => $proveedor,
                'fecha' => $fecha->toDateString(),
                'monto' => $comprometido,
                'created_by' => auth()->id(),
            ]);

            $imputaciones = app(ImputacionService::class);
            $imputaciones->destinar($gasto, new DestinoPartida($partida->id, $motivoId, $comentario, null, app(ProyectosService::class)), $fecha, 'PEN');

            foreach ([[MomentoImputacion::Comprometido, $comprometido], [MomentoImputacion::Ejecutado, $ejecutado], [MomentoImputacion::Pagado, $pagado]] as [$momento, $monto]) {
                if (bccomp($monto, '0', 2) > 0) {
                    $imputaciones->mover($gasto, $momento, $monto);
                }
            }

            return $gasto;
        });
    }

    /**
     * MVP rápido (t2-contratos): un costo a los gastos generales del proyecto (la comisión de una carta fianza, v4
     * flujo 1, paso 2). Null si el proyecto aún no tiene árbol aprobado con su partida de gastos generales.
     */
    public function enGastosGenerales(int $proyectoId, string $descripcion, string $proveedor, CarbonImmutable $fecha, string $monto): ?GastoDemo
    {
        $partida = PartidaControl::query()->where('proyecto_id', $proyectoId)->where('es_gastos_generales', true)->where('es_hoja', true)->where('es_activa', true)
            ->whereHas('arbol.estado', fn ($q) => $q->where('codigo', 'aprobado'))->orderBy('codigo')->first();

        return $partida === null ? null : $this->registrar($partida->id, $descripcion, $proveedor, $fecha, $monto, $monto, $monto);
    }
}
