<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Tablero;

use App\Modules\Erp\Proyectos\Estados\DefinicionProyecto;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Resumen\AvisoResumen;
use App\Modules\Erp\Proyectos\Resumen\ContenidoTarjeta;
use App\Modules\Erp\Proyectos\Resumen\SeccionResumen;
use App\Modules\Erp\Proyectos\Resumen\TarjetaResumen;
use App\Modules\Nucleo\Models\User;

/** En «Atención» (plan 2.61, D6): el jefe de un proyecto vivo ya no está activo, como el aviso de la portada. */
final class TarjetaJefeInactivo implements TarjetaResumen
{
    public function clave(): string
    {
        return 'jefe-inactivo';
    }

    public function seccion(): SeccionResumen
    {
        return SeccionResumen::Atencion;
    }

    public function orden(): int
    {
        return 10;
    }

    public function exigeCostos(): bool
    {
        return false;
    }

    public function contenido(int $proyectoId, User $usuario): ?ContenidoTarjeta
    {
        $proyecto = Proyecto::query()->with('estado')->find($proyectoId);

        if ($proyecto === null || ! in_array($proyecto->estado->codigo, DefinicionProyecto::ACEPTAN_COSTOS, true)) {
            return null;
        }

        $jefe = User::query()->find($proyecto->jefe_proyecto_user_id);

        if ($jefe === null || $jefe->es_activo) {
            return null;
        }

        return new ContenidoTarjeta('Jefe de proyecto', avisos: [
            new AvisoResumen("El jefe del proyecto, {$jefe->name}, ya no está activo: asigna otro.", 'danger'),
        ]);
    }
}
