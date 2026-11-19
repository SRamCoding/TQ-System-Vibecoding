<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

/**
 * La forma de la respuesta que se pide al modelo (plan 1.11, D6) y su revisión al volver. Por cada campo que propone el
 * proceso: su valor, su evidencia (con adjunto, página y texto exacto; con registros, el campo que lo respalda) y la
 * seguridad que el modelo le da. Además, una explicación corta.
 */
final class SalidaIa
{
    public const int MAXIMO_EXPLICACION = 300;

    /** @return array<string, mixed> */
    public static function esquema(DefinicionProcesoIa $proceso): array
    {
        $campos = [];

        foreach ($proceso->campos as $campo => $valor) {
            $campos[$campo] = self::objeto([
                'valor' => $valor,
                'evidencia' => self::objeto([
                    'pagina' => ['type' => ['integer', 'null']],
                    'texto' => ['type' => ['string', 'null']],
                    'campo' => ['type' => ['string', 'null']],
                ]),
                'seguridad' => ['type' => 'string', 'enum' => ['alta', 'media', 'baja']],
            ]);
        }

        return self::objeto(['campos' => self::objeto($campos), 'explicacion' => ['type' => 'string']]);
    }

    /**
     * Si la salida tiene la forma pedida y una explicación de hasta 300 caracteres.
     *
     * @param  array<string, mixed>|null  $salida
     */
    public static function esValida(DefinicionProcesoIa $proceso, ?array $salida): bool
    {
        if ($salida === null || ! ValidadorEsquemaIa::valida($salida, self::esquema($proceso))) {
            return false;
        }

        $explicacion = trim((string) $salida['explicacion']);

        return $explicacion !== '' && mb_strlen($explicacion) <= self::MAXIMO_EXPLICACION;
    }

    /**
     * @param  array<string, mixed>  $propiedades
     * @return array<string, mixed>
     */
    private static function objeto(array $propiedades): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => array_keys($propiedades), 'properties' => $propiedades];
    }
}
