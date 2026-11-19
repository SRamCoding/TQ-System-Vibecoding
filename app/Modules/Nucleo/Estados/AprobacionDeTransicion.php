<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Estados;

use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Las aprobaciones vistas desde las transiciones (plan 1.5, D7; plan 1.7). `TransicionesService` pregunta si una
 * transición con aprobación ya tiene sus firmas y avisa cada cambio de estado, para que se abran y cierren los pedidos
 * de aprobación. La implementación está en el contenedor (`ReglasDeAprobacion`).
 */
interface AprobacionDeTransicion
{
    /** Nulo si se completaron las firmas; si no, por qué no se puede ejecutar todavía. */
    public function pendiente(Model $documento, TransicionEstado $transicion): ?string;

    /**
     * El documento acaba de llegar a un estado (también al crearse: `$hecha` nula), dentro de la misma transacción.
     * Cierra los pedidos que ya no corresponden y abre uno por cada transición con aprobación que sale del estado nuevo.
     *
     * @param  User  $quien  quien hizo el cambio (o lo originó, si lo hizo el sistema): el que pide la aprobación
     */
    public function alCambiarEstado(Model&DocumentoConEstados $documento, ?TransicionEstado $hecha, User $quien): void;
}
