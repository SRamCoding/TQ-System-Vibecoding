<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Preferencias\FuenteDeLista;
use App\Modules\Nucleo\Services\NavegacionService;

/**
 * La preferencia de lista «Módulos de la barra» (plan 1.4g, D3): lo elegido son los módulos a la vista; lo que se
 * guarda, en `modulos_ocultos`, son los que la persona sacó (plan 1.4f, D4). Se cambia con `nucleo.elegir-modulos`.
 */
final readonly class ModulosDeLaBarra implements FuenteDeLista
{
    public function __construct(private NavegacionService $navegacion) {}

    public function valores(User $usuario): array
    {
        return array_map(fn (CajaModulo $caja): string => $caja->clave, $this->navegacion->deLaBarra($usuario));
    }

    public function opciones(User $usuario): array
    {
        // MV-016: la barra muestra las cajas de proceso, no las pantallas.
        $opciones = [];

        foreach ($this->navegacion->cajasDeProceso($usuario) as $caja) {
            $opciones[$caja->clave] = $caja->nombre;
        }

        return $opciones;
    }

    public function restablecer(User $usuario): void
    {
        $this->navegacion->ocultar($usuario, []);
    }

    public function accion(): string
    {
        return 'nucleo.elegir-modulos';
    }

    public function datosParaGuardar(array $elegidos, array $mostrados): array
    {
        return ['ocultos' => array_values(array_diff($mostrados, $elegidos))];
    }
}
