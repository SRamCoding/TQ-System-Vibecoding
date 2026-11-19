<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Preferencias;

/**
 * Cómo se lee una preferencia (plan 1.4g, D2). En la base el valor es texto; aquí, su forma en PHP. Nunca `float`: un
 * número es entero. Una lista no se guarda en `preferencias_usuario`: vive en la tabla de su módulo (D3).
 */
enum TipoPreferencia: string
{
    case SiNo = 'si_no';
    case Numero = 'numero';
    case Opcion = 'opcion';
    case Lista = 'lista';

    /** Valor en PHP → texto que se guarda. */
    public function aTexto(bool|int|string $valor): string
    {
        return match (true) {
            is_bool($valor) => $valor ? 'true' : 'false',
            default => (string) $valor,
        };
    }

    /**
     * Lo que llega (de la acción, en texto, o del código, ya en su tipo) → valor en PHP, o null si no corresponde al
     * tipo. Si está entre las opciones o los límites lo decide la definición.
     */
    public function interpretar(mixed $entrada): bool|int|string|null
    {
        return match ($this) {
            self::SiNo => match (true) {
                is_bool($entrada) => $entrada,
                in_array($entrada, ['true', '1', 1], true) => true,
                in_array($entrada, ['false', '0', 0], true) => false,
                default => null,
            },
            self::Numero => match (true) {
                is_int($entrada) => $entrada,
                is_string($entrada) && preg_match('/^-?\d{1,9}$/', $entrada) === 1 => (int) $entrada,
                default => null,
            },
            self::Opcion => is_string($entrada) && $entrada !== '' ? $entrada : null,
            self::Lista => null,
        };
    }
}
