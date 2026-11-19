<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Seguridad;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\SeguridadService;
use Illuminate\Database\Eloquent\Model;

/**
 * Oculta cada campo sensible del modelo cuyo permiso `campos.ver-<grupo>` no tiene la persona por ninguno de sus
 * roles (plan 1.4, D2 y D6). Un modelo que no declara campos sensibles se ve entero.
 */
final class CamposVisiblesPorPermiso implements CamposVisibles
{
    public function __construct(private readonly SeguridadService $seguridad) {}

    public function ocultos(User $usuario, Model $modelo): array
    {
        if (! $modelo instanceof DeclaraCamposSensibles) {
            return [];
        }

        $permitidos = [];
        $ocultos = [];

        foreach ($modelo->camposSensibles() as $columna => $grupo) {
            $permitidos[$grupo->value] ??= $this->seguridad->puede($usuario, $grupo->permiso());

            if (! $permitidos[$grupo->value]) {
                $ocultos[] = $columna;
            }
        }

        return $ocultos;
    }
}
