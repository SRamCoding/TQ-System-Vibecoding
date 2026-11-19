<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

/**
 * La animación con que termina el ícono de una caja del inicio, después de sus preguntas (plan 1.4q, D6 y D7). Los estilos
 * están en `widgets/cajas-modulos.blade.php`; algunas mueven piezas del ícono, que el SVG marca con su clase.
 */
enum AnimacionCaja: string
{
    /** Por defecto (D7): el ícono late suave. Sirve para cualquier ícono. */
    case Pulso = 'pulso';

    /** «Mien»: la vista sube como una cámara y aparece la estrella, que queda brillando. Cualquier ícono. */
    case Camara = 'camara';

    /** «Personas y empresas»: las manos llegan desde los costados y se estrechan. Piezas `tq-mano-izq` y `tq-mano-der`. */
    case Apreton = 'apreton';

    /**
     * «Usuarios y organigrama»: aparece la persona del medio, luego la de la derecha, luego la de la izquierda. Piezas
     * `tq-persona-centro`, `tq-persona-der` y `tq-persona-izq`.
     */
    case Personas = 'personas';

    /** «Configuración»: la rueda gira y frena. Cualquier ícono. */
    case Rueda = 'rueda';
}
