<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Historial;

/**
 * Un modelo con bitácora que sabe mostrarla a una persona: el nombre de cada campo, sus valores legibles y
 * de qué parte de la ficha se trata (plan 1.3, D10). La implementación por defecto está en DescribeBitacora.
 */
interface DescribibleEnBitacora
{
    /**
     * Nombre legible de cada columna. Una que falte se muestra con el nombre de la columna: nada desaparece.
     *
     * @return array<string, string>
     */
    public function etiquetasBitacora(): array;

    /** De qué parte de la ficha es el cambio («Datos», «Rol Cliente»). */
    public function sujetoBitacora(): string;

    /** Un valor guardado en la bitácora, como lo lee una persona. */
    public function valorLegibleBitacora(string $campo, mixed $valor): string;

    /**
     * La creación en una sola frase («Asignó rol Cliente desde 04/10/2026»), o nulo para mostrarla campo por
     * campo. Sirve para las partes de una ficha, donde repetir a quién pertenecen sobra.
     *
     * @param  array<string, mixed>  $valores  los guardados al crear
     */
    public function resumenCreacionBitacora(array $valores): ?string;
}
