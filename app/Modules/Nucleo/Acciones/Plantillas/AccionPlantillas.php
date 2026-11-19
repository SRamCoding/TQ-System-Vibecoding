<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Plantillas;

use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Base de las acciones de plantillas (ADR 0022; plan 2.11, paso 4). Sin permiso propio: el permiso depende del tipo
 * de la plantilla (usar y publicar, D1 y D2), y lo revisa PlantillasService con la definición del tipo, como las
 * aplicaciones propias del panel. Las reglas están en el servicio.
 */
abstract class AccionPlantillas extends Accion
{
    protected static function campoPlantilla(): Campo
    {
        return Campo::entero('plantilla_id', 'Plantilla')->conReglas('min:1');
    }

    protected static function campoNombre(): Campo
    {
        return Campo::texto('nombre', 'Nombre', maximo: 160);
    }

    protected static function campoDescripcion(): Campo
    {
        return Campo::texto('descripcion', 'Para qué sirve', maximo: 1000)->opcional();
    }

    protected static function campoDocumento(): Campo
    {
        return Campo::entero('documento_id', 'Documento')->conReglas('min:1');
    }

    protected static function campoVersion(): Campo
    {
        return Campo::entero('version', 'Versión')->conReglas('min:1')->deControl();
    }
}
