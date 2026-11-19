<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

/**
 * Elige un elemento por año (plan 1.4d, D7): la rotación empieza en el año más un desfase propio de la semilla (la
 * fecha), como RegistroAmbientaciones, y salta lo que se eligió el año anterior. Con dos o más elementos, nunca repite
 * aunque la lista haya cambiado; con uno, no hay otro remedio.
 */
final class RotacionAnual
{
    /**
     * @template T of string|int
     *
     * @param  list<T>  $elementos  en un orden estable (de registro o por código)
     * @param  T|null  $anterior  lo elegido el año anterior, si se guardó
     * @return T|null
     */
    public static function elegir(array $elementos, int $anio, string $semilla, string|int|null $anterior = null): string|int|null
    {
        $cantidad = count($elementos);

        if ($cantidad === 0) {
            return null;
        }

        $inicio = ($anio + crc32($semilla)) % $cantidad;

        for ($paso = 0; $paso < $cantidad; $paso++) {
            $elemento = $elementos[($inicio + $paso) % $cantidad];

            if ($elemento !== $anterior) {
                return $elemento;
            }
        }

        return $elementos[$inicio];
    }
}
