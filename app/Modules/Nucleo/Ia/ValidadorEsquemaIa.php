<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

/**
 * Revisa un valor contra la parte de JSON Schema que usan los procesos de IA (plan 1.11, D8): `type` (uno o varios),
 * `enum`, `properties`, `required`, `additionalProperties`, `items`, `minimum`, `maximum`, `maxLength` y `pattern`. La
 * salida estructurada del proveedor ya respeta el esquema; esto es la revisión al volver, que no confía en él.
 */
final class ValidadorEsquemaIa
{
    /** @param array<string, mixed> $esquema */
    public static function valida(mixed $valor, array $esquema): bool
    {
        $tipos = (array) ($esquema['type'] ?? []);

        if ($tipos !== [] && ! array_any($tipos, fn (mixed $tipo): bool => self::esDelTipo($valor, (string) $tipo))) {
            return false;
        }

        if (isset($esquema['enum']) && ! in_array($valor, (array) $esquema['enum'], true)) {
            return false;
        }

        if (is_string($valor)) {
            return self::textoValido($valor, $esquema);
        }

        if (is_int($valor) || is_float($valor)) {
            return (! isset($esquema['minimum']) || $valor >= $esquema['minimum']) && (! isset($esquema['maximum']) || $valor <= $esquema['maximum']);
        }

        if (is_array($valor) && array_is_list($valor) && in_array('array', $tipos, true)) {
            return ! isset($esquema['items']) || array_all($valor, fn (mixed $item): bool => self::valida($item, (array) $esquema['items']));
        }

        return ! is_array($valor) || self::objetoValido($valor, $esquema);
    }

    private static function esDelTipo(mixed $valor, string $tipo): bool
    {
        return match ($tipo) {
            'string' => is_string($valor),
            'integer' => is_int($valor),
            'number' => is_int($valor) || is_float($valor),
            'boolean' => is_bool($valor),
            'null' => $valor === null,
            'array' => is_array($valor) && array_is_list($valor),
            'object' => is_array($valor) && ($valor === [] || ! array_is_list($valor)),
            default => false,
        };
    }

    /** @param array<string, mixed> $esquema */
    private static function textoValido(string $valor, array $esquema): bool
    {
        return (! isset($esquema['maxLength']) || mb_strlen($valor) <= (int) $esquema['maxLength'])
            && (! isset($esquema['pattern']) || preg_match('/'.str_replace('/', '\/', (string) $esquema['pattern']).'/u', $valor) === 1);
    }

    /**
     * @param  array<array-key, mixed>  $valor
     * @param  array<string, mixed>  $esquema
     */
    private static function objetoValido(array $valor, array $esquema): bool
    {
        /** @var array<string, array<string, mixed>> $propiedades */
        $propiedades = (array) ($esquema['properties'] ?? []);

        foreach ((array) ($esquema['required'] ?? []) as $requerido) {
            if (! array_key_exists((string) $requerido, $valor)) {
                return false;
            }
        }

        foreach ($valor as $clave => $dato) {
            if (! isset($propiedades[$clave])) {
                if (($esquema['additionalProperties'] ?? true) === false) {
                    return false;
                }

                continue;
            }

            if (! self::valida($dato, $propiedades[$clave])) {
                return false;
            }
        }

        return true;
    }
}
