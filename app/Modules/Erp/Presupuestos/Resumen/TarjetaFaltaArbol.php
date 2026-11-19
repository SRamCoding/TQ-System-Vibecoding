<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Resumen;

use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\ConsultaArbol;
use App\Modules\Erp\Proyectos\Resumen\AvisoResumen;
use App\Modules\Erp\Proyectos\Resumen\ContenidoTarjeta;
use App\Modules\Erp\Proyectos\Resumen\SeccionResumen;
use App\Modules\Erp\Proyectos\Resumen\TarjetaResumen;
use App\Modules\Nucleo\Models\User;

/** En «Atención» (plan 2.61, D6): el proyecto todavía no tiene árbol de partidas, así que no recibe costos. Sin montos. */
final class TarjetaFaltaArbol implements TarjetaResumen
{
    public function clave(): string
    {
        return 'falta-arbol';
    }

    public function seccion(): SeccionResumen
    {
        return SeccionResumen::Atencion;
    }

    public function orden(): int
    {
        return 30;
    }

    public function exigeCostos(): bool
    {
        return false;
    }

    public function contenido(int $proyectoId, User $usuario): ?ContenidoTarjeta
    {
        if (! $usuario->can(PresupuestosServiceProvider::PERMISO_VER) || app(ConsultaArbol::class)->arbolDe($proyectoId) !== null) {
            return null;
        }

        return new ContenidoTarjeta('Partidas de control', avisos: [
            new AvisoResumen('Falta armar el árbol de partidas: sin él, el proyecto no recibe costos.'),
        ]);
    }
}
