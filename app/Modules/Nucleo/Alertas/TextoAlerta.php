<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Alertas;

/**
 * Los textos de una alerta (plan 1.10, D5): palabras libres y marcadores `{dato}` que el sistema llena con el valor
 * real. Un texto vale si trae cada dato de su alerta (en el asunto o en el texto) y ningún marcador que no exista.
 */
final class TextoAlerta
{
    /**
     * Los datos que no aparecen ni en el asunto ni en el texto.
     *
     * @param  list<string>  $datos
     * @return list<string>
     */
    public static function faltantes(string $asunto, string $texto, array $datos): array
    {
        $usados = self::marcadores($asunto.' '.$texto);

        return array_values(array_diff($datos, $usados));
    }

    /**
     * Los marcadores escritos que no son datos de la alerta.
     *
     * @param  list<string>  $datos
     * @return list<string>
     */
    public static function desconocidos(string $asunto, string $texto, array $datos): array
    {
        return array_values(array_diff(self::marcadores($asunto.' '.$texto), $datos));
    }

    /**
     * El texto con cada marcador cambiado por su valor.
     *
     * @param  array<string, string|int>  $valores
     */
    public static function llenar(string $plantilla, array $valores): string
    {
        return strtr($plantilla, collect($valores)->mapWithKeys(fn (string|int $valor, string $dato): array => ['{'.$dato.'}' => (string) $valor])->all());
    }

    /** «60,30,15»: días enteros, de 0 a 999, sin repetir. */
    public static function anticipacionValida(string $dias): bool
    {
        if (preg_match('/^[0-9]{1,3}(,[0-9]{1,3})*$/', $dias) !== 1) {
            return false;
        }

        $lista = explode(',', $dias);

        return count($lista) === count(array_unique(array_map('intval', $lista)));
    }

    /** @return list<string> */
    private static function marcadores(string $texto): array
    {
        preg_match_all('/\{([^{}]*)\}/', $texto, $coincidencias);

        return array_values(array_unique($coincidencias[1]));
    }
}
