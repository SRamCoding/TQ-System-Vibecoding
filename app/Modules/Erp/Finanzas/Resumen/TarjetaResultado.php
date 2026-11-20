<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Resumen;

use App\Modules\Erp\Finanzas\Services\FinanzasService;
use App\Modules\Erp\Presupuestos\Resumen\Soles;
use App\Modules\Erp\Proyectos\Resumen\ContenidoTarjeta;
use App\Modules\Erp\Proyectos\Resumen\LineaResumen;
use App\Modules\Erp\Proyectos\Resumen\SeccionResumen;
use App\Modules\Erp\Proyectos\Resumen\TarjetaResumen;
use App\Modules\Nucleo\Models\User;

/** MVP rápido (t5-finanzas): «¿Cuánto se ganó de verdad?» en el tablero del proyecto. */
final class TarjetaResultado implements TarjetaResumen
{
    public function clave(): string
    {
        return 'resultado';
    }

    public function seccion(): SeccionResumen
    {
        return SeccionResumen::Cobranza;
    }

    public function orden(): int
    {
        return 10;
    }

    public function exigeCostos(): bool
    {
        return true;
    }

    public function contenido(int $proyectoId, User $usuario): ?ContenidoTarjeta
    {
        if (! $usuario->can(FinanzasService::PERMISO_VER)) {
            return null;
        }

        $r = app(FinanzasService::class)->resultado($proyectoId);
        $rojo = bccomp($r['margen_real'], '0', 2) < 0 ? 'danger' : 'success';

        return new ContenidoTarjeta('Resultado real', [
            new LineaResumen('Facturado', Soles::texto($r['facturado'])),
            new LineaResumen('Por cobrar', Soles::texto($r['por_cobrar']), bccomp($r['por_cobrar'], '0', 2) > 0 ? 'warning' : null),
            new LineaResumen('Costo real (ejecutado)', Soles::texto($r['costo'])),
            new LineaResumen('Por pagar a proveedores', Soles::texto($r['por_pagar'])),
            new LineaResumen('Margen real', Soles::texto($r['margen_real']), $rojo, [], $r['margen_pct'] === null ? null : "{$r['margen_pct']} % de lo facturado"),
            new LineaResumen('Caja neta del proyecto', Soles::texto($r['caja']), bccomp($r['caja'], '0', 2) < 0 ? 'warning' : null),
        ]);
    }
}
