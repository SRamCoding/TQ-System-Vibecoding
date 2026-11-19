<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Condiciones;

use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/** No se aprueba el árbol de un proyecto cerrado o anulado (plan 2.1, D5). Lo pregunta a C2. */
final readonly class ProyectoDelArbolAceptaCostos implements CondicionTransicion
{
    public function __construct(private ProyectosService $proyectos) {}

    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        $proyecto = $this->proyectos->resumen((int) $documento->getAttribute('proyecto_id'));

        return $proyecto->aceptaCostos ? null : "El proyecto {$proyecto->codigo} está ".mb_strtolower($proyecto->estadoNombre).': su árbol ya no se aprueba.';
    }
}
