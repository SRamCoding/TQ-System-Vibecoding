<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Resumen;

use App\Modules\Nucleo\Models\User;

/**
 * Una tarjeta que un módulo suma al Resumen de la ficha del proyecto (plan 2.61, D6), como las pestañas de la 2.10: lee
 * sus propios datos por sus servicios, nunca los modelos de Proyectos. Contratos, valorizaciones, garantías, fases,
 * compras y tareo suman la suya cuando nazcan, sin tocar la ficha.
 */
interface TarjetaResumen
{
    /** Minúsculas, cifras y guiones; única. */
    public function clave(): string;

    public function seccion(): SeccionResumen;

    /** Menor, más arriba dentro de su sección. */
    public function orden(): int;

    /** Si lleva montos de costo (ADR 0016, D3): sin `campos.ver-costos` no se pide ni se dibuja. */
    public function exigeCostos(): bool;

    /** Lo que dice hoy para ese proyecto, o null si no tiene nada que decir. */
    public function contenido(int $proyectoId, User $usuario): ?ContenidoTarjeta;
}
