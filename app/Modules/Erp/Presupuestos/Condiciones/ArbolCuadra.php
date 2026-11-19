<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Condiciones;

use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Nucleo\Estados\CondicionTransicion;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Un árbol se aprueba solo si cuadra (v4, parte 6): al menos una hoja y la suma de las hojas igual al costo
 * presupuestado. Corre con la fila del árbol bloqueada por `TransicionesService`; quien cambia un monto la bloquea
 * igual (D5), así que la suma que se revisa es la de ahora.
 */
final readonly class ArbolCuadra implements CondicionTransicion
{
    public function __construct(private ArbolPartidasService $arboles) {}

    public function revisar(Model $documento, User $usuario, TransicionEstado $transicion): ?string
    {
        $arbolId = (int) $documento->getKey();

        if ($this->arboles->hojasActivas($arbolId) === 0) {
            return 'El árbol no tiene ninguna partida hoja: no hay contra qué cargar el costo.';
        }

        $suma = $this->arboles->sumaDeHojas($arbolId);
        $costo = (string) $documento->getAttribute('costo_presupuestado');

        return bccomp($suma, $costo, 2) === 0
            ? null
            : "El árbol no cuadra: sus hojas suman S/ {$suma} y el costo presupuestado es S/ {$costo}.";
    }
}
