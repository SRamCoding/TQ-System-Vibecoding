<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Resumen;

use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\DineroDelProyecto;
use App\Modules\Erp\Proyectos\Resumen\AvisoResumen;
use App\Modules\Erp\Proyectos\Resumen\ContenidoTarjeta;
use App\Modules\Erp\Proyectos\Resumen\SeccionResumen;
use App\Modules\Erp\Proyectos\Resumen\TarjetaResumen;
use App\Modules\Nucleo\Models\User;

/** En «Atención» (plan 2.61, D6): las partidas cuyo comprometido pasa lo presupuestado. Dice cuánto: lleva costos (D3). */
final class TarjetaPartidasExcedidas implements TarjetaResumen
{
    public function clave(): string
    {
        return 'partidas-excedidas';
    }

    public function seccion(): SeccionResumen
    {
        return SeccionResumen::Atencion;
    }

    public function orden(): int
    {
        return 20;
    }

    public function exigeCostos(): bool
    {
        return true;
    }

    public function contenido(int $proyectoId, User $usuario): ?ContenidoTarjeta
    {
        if (! $usuario->can(PresupuestosServiceProvider::PERMISO_VER) || ($d = app(DineroDelProyecto::class)->de($proyectoId)) === null) {
            return null;
        }

        $avisos = [];

        foreach ($d['partidas'] as $p) {
            if (! $p['es_no_presupuestado'] && $p['saldo']->excedida()) {
                $avisos[] = new AvisoResumen("{$p['codigo']} · {$p['nombre']}: excedida en ".Soles::texto(bcmul($p['saldo']->disponible, '-1', 2)).'.', 'danger');
            }
        }

        return $avisos === [] ? null : new ContenidoTarjeta('Partidas excedidas', avisos: $avisos);
    }
}
