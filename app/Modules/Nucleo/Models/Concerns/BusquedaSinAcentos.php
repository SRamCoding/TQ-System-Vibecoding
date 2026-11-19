<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Búsqueda humana: sin distinguir mayúsculas ni acentos (`unaccent`, docs/convenciones/base-de-datos.md,
 * «Búsquedas»; plan 1.3, D11). Lo que escribe la persona va siempre como parámetro, y sus `%` y `_` se
 * buscan tal cual, no como comodines. Un texto vacío no filtra. Los modelos la usan con el scope de
 * BuscaSinAcentos; una consulta de modelo variable (Catálogos), directo.
 */
final class BusquedaSinAcentos
{
    /**
     * Alguna de las columnas contiene el texto.
     *
     * @template TModelo of Model
     *
     * @param  Builder<TModelo>  $consulta
     * @param  list<string>  $columnas  nombres fijos del código, nunca lo que llega del usuario
     */
    public static function aplicar(Builder $consulta, ?string $texto, array $columnas): void
    {
        $texto = trim((string) $texto);

        if ($texto === '' || $columnas === []) {
            return;
        }

        $patron = '%'.self::escaparComodines($texto).'%';

        $consulta->where(function (Builder $q) use ($columnas, $patron): void {
            foreach ($columnas as $columna) {
                // Las columnas salen de una lista fija; igual se escapan (defensa en profundidad).
                $q->orWhereRaw('unaccent('.$q->getGrammar()->wrap($columna).') ilike unaccent(?)', [$patron]);
            }
        });
    }

    /** `%`, `_` y la barra invertida del texto se buscan tal cual (la barra es el escape de PostgreSQL). */
    public static function escaparComodines(string $texto): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $texto);
    }
}
