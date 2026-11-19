<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aplicaciones;

use App\Modules\Nucleo\Aplicaciones\DatosAplicacionPropia;
use App\Modules\Nucleo\Enums\IconoAplicacion;
use App\Modules\Nucleo\Enums\ProgramaEscritorio;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\AplicacionesService;

/**
 * Base de las acciones con que cada persona arma su panel de aplicaciones (plan 1.4p, D5 y D7): sus propias, las de
 * Techquk que oculta y el uso que decide el muelle. Sin permiso: es su propio panel. Las de todos las pone
 * administración en Configuración → Catálogos. Las reglas están en AplicacionesService.
 */
abstract class AccionAplicaciones extends Accion
{
    public function __construct(protected readonly AplicacionesService $aplicaciones) {}

    /**
     * @return list<Campo>
     */
    protected static function camposDeDatos(): array
    {
        return [
            Campo::texto('nombre', 'Nombre', maximo: 40),
            Campo::texto('enlace', 'Enlace (https://…)', maximo: 300),
            Campo::opcion('icono', 'Ícono', IconoAplicacion::class),
            // Plan 1.4z, D6: de la lista cerrada; nunca un enlace escrito a mano.
            Campo::opcion('escritorio', 'Programa de escritorio', ProgramaEscritorio::class)->opcional(),
        ];
    }

    protected static function campoPropia(): Campo
    {
        return Campo::entero('aplicacion_id', 'Aplicación')->conReglas('min:1');
    }

    protected static function campoVersion(): Campo
    {
        return Campo::entero('version', 'Versión')->conReglas('min:1')->deControl();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected static function datos(array $datos): DatosAplicacionPropia
    {
        return new DatosAplicacionPropia($datos['nombre'], $datos['enlace'], $datos['icono'], $datos['escritorio'] ?? null);
    }
}
