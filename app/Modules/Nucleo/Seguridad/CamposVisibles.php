<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Seguridad;

use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Qué campos de un registro no puede ver una persona. Lo pregunta todo lo que muestra datos fuera de la ficha
 * misma, empezando por la pestaña de historial: si no, un cambio de costo o de sueldo se vería ahí aunque la
 * ficha lo oculte (plan 1.3, D10). Se filtra en el servidor: lo oculto nunca llega al navegador.
 */
interface CamposVisibles
{
    /**
     * @return list<string> las columnas que la persona no puede ver
     */
    public function ocultos(User $usuario, Model $modelo): array;
}
