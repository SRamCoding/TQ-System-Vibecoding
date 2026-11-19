<?php

declare(strict_types=1);

namespace Tests\Modules\Erp\Presupuestos\Fixtures;

use App\Modules\Nucleo\Imputacion\EleccionImputacion;
use App\Modules\Nucleo\Imputacion\FuenteDeDestinos;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ExcesoDePartidaService;
use App\Modules\Nucleo\Services\ImputacionService;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * El servicio de la orden de prueba, como lo hará el de un documento con costo real (costura C12): la línea y su destino
 * en una sola transacción (paso a) y la revisión del exceso al enviar (paso b).
 */
final class ServicioOrdenDePrueba
{
    public function crear(?int $unidadId, User $usuario): OrdenDePrueba
    {
        return app(TransicionesService::class)->crear(new OrdenDePrueba(['nombre' => 'Orden de cables', 'unidad_id' => $unidadId]), $usuario)->refresh();
    }

    /** Si el destino falla, la línea no queda (plan 2.2, D2). */
    public function agregarLinea(int $ordenId, string $descripcion, string $monto, string $moneda, CarbonImmutable $fecha, EleccionImputacion $eleccion): LineaOrdenDePrueba
    {
        return DB::transaction(function () use ($ordenId, $descripcion, $monto, $moneda, $fecha, $eleccion): LineaOrdenDePrueba {
            $orden = OrdenDePrueba::query()->lockForUpdate()->findOrFail($ordenId);
            $linea = LineaOrdenDePrueba::query()->create(['orden_prueba_id' => $orden->id, 'descripcion' => $descripcion, 'monto' => $monto, 'moneda' => $moneda, 'fecha' => $fecha->toDateString()]);
            app(ImputacionService::class)->destinar($linea, app(FuenteDeDestinos::class)->destino($eleccion), $fecha, $moneda);

            return $linea;
        });
    }

    public function enviar(int $ordenId, User $usuario): void
    {
        DB::transaction(function () use ($ordenId, $usuario): void {
            $orden = OrdenDePrueba::query()->lockForUpdate()->findOrFail($ordenId);
            app(ExcesoDePartidaService::class)->alEnviar((new DefinicionOrdenDePrueba)->lineasDeCosto($orden));
            app(TransicionesService::class)->transicionar($orden, 'enviar', $orden->version, $usuario);
        });
    }
}
