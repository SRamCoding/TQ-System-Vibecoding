<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Preferencias;

use App\Modules\Nucleo\Models\User;

/**
 * El módulo dueño de una preferencia de tipo lista (plan 1.4g, D3): la lista vive en su propia tabla, con una fila por
 * elemento y su unicidad en la base. Se cambia con la acción propia del módulo; el registro solo la lee y la
 * restablece.
 */
interface FuenteDeLista
{
    /**
     * Las claves elegidas hoy por la persona.
     *
     * @return list<string>
     */
    public function valores(User $usuario): array;

    /**
     * Las claves que se pueden elegir, con su nombre para la pantalla.
     *
     * @return array<string, string>
     */
    public function opciones(User $usuario): array;

    /** Vuelve la lista a como viene (vacía de lo que la persona cambió). */
    public function restablecer(User $usuario): void;

    /** La clave de la acción registrada del módulo que guarda la lista (plan 1.4g, D3). */
    public function accion(): string;

    /**
     * Los datos de esa acción para lo que la persona dejó elegido. Lo no elegido se calcula contra lo que se le mostró
     * al abrir la pantalla, no contra lo de ahora: una opción nueva mientras la pantalla está abierta no se pierde por
     * accidente (como «Elegir módulos», plan 1.4f).
     *
     * @param  list<string>  $elegidos
     * @param  list<string>  $mostrados
     * @return array<string, mixed>
     */
    public function datosParaGuardar(array $elegidos, array $mostrados): array;
}
