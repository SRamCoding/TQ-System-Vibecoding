<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Salud;

/**
 * Un recuadro de la página de salud (plan 1.12, D2). El núcleo trae los suyos; cada módulo registra los propios en
 * `RegistroSalud` desde su provider, con `callAfterResolving` como el núcleo, (las exportaciones contables de la etapa 4, por ejemplo) sin tocar la página.
 * `revisar()` solo lee: nunca escribe ni llama a un servicio externo.
 */
interface ComprobacionSalud
{
    /** Única y estable: `modulo.cosa`. */
    public function clave(): string;

    public function titulo(): string;

    /** Un heroicon que no use ningún otro recuadro. */
    public function icono(): string;

    public function revisar(): ResultadoComprobacion;
}
