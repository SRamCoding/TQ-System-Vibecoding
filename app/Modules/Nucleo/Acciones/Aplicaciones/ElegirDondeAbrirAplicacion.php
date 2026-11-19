<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Aplicaciones;

use App\Modules\Nucleo\Enums\OrigenAplicacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/** La persona elige si abre una aplicación en su programa de escritorio o en la web (plan 1.4z, D6). */
final class ElegirDondeAbrirAplicacion extends AccionAplicaciones
{
    public function clave(): string
    {
        return 'nucleo.elegir-donde-abrir-aplicacion';
    }

    public function nombre(): string
    {
        return 'Abrir en el escritorio o en la web';
    }

    public function campos(): array
    {
        return [
            Campo::opcion('origen', 'De dónde es', OrigenAplicacion::class),
            Campo::entero('aplicacion_id', 'Aplicación')->conReglas('min:1'),
            Campo::booleano('en_escritorio', 'En el escritorio'),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->aplicaciones->abrirEn($usuario, $datos['origen'], $datos['aplicacion_id'], $datos['en_escritorio']);

        return null;
    }
}
