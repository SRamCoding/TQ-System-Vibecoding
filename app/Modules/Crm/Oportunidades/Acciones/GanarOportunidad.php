<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Services\DatosDelProyectoAlGanar;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** Ganar (plan 2.21, D3): la fecha de cierre real y el monto final. La acción propia de la transición «ganar». */
final class GanarOportunidad extends Accion
{
    public function clave(): string
    {
        return DefinicionOportunidad::CLAVE.'.ganar';
    }

    public function nombre(): string
    {
        return 'Ganar oportunidad';
    }

    public function campos(): array
    {
        return [
            Campo::entero('oportunidad_id', 'Oportunidad')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl(),
            Campo::fecha('fecha_cierre_real', 'Fecha de cierre'),
            // Plan 2.22, P5: con cotización aceptada va vacío (es su total) o igual a él.
            Campo::decimal('monto_final', 'Monto final')->opcional(),
            Campo::texto('comentario', 'Comentario', maximo: 1000)->opcional(),
            // MV-006: el proyecto nace al ganar; esto es lo que pide.
            Campo::entero('jefe_proyecto_user_id', 'Jefe de proyecto')->conReglas('min:1'),
            Campo::fecha('fecha_inicio', 'Inicio del proyecto'),
            Campo::fecha('fecha_fin_prevista', 'Fin previsto')->conReglas('after_or_equal:fecha_inicio'),
        ];
    }

    public function permiso(): string
    {
        return $this->clave();
    }

    public function ejecutar(array $datos, User $usuario): HistorialEstado
    {
        return app(OportunidadesService::class)->ganar(
            $datos['oportunidad_id'], $datos['version'], $datos['fecha_cierre_real'], $datos['monto_final'] ?? null, $datos['comentario'] ?? null, $usuario,
            new DatosDelProyectoAlGanar($datos['jefe_proyecto_user_id'], $datos['fecha_inicio'], $datos['fecha_fin_prevista']),
        );
    }
}
