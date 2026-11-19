<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Resumen;

/**
 * Una opción del filtro «Salud» de la portada (plan 2.61, D4): «Vencidos», «Margen en riesgo», «Con partidas
 * excedidas»… La declara el módulo que sabe el dato; Proyectos solo restringe la lista a los ids que devuelve, en la
 * base. Las de costos (D3) no se ofrecen a quien no los ve.
 */
interface FiltroSalud
{
    /** Minúsculas, cifras y guiones; única. */
    public function clave(): string;

    public function etiqueta(): string;

    /** `danger` o `warning`: el punto de color junto a la opción. */
    public function tono(): string;

    public function exigeCostos(): bool;

    /** El permiso del módulo que da el dato, además de ver costos (el mismo que piden sus tarjetas), o null. */
    public function permiso(): ?string;

    /**
     * Los proyectos que cumplen, hoy.
     *
     * @return list<int>
     */
    public function proyectos(): array;
}
