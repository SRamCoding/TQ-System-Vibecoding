<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

use App\Modules\Nucleo\RegistroAcciones\Campo;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use LogicException;

/**
 * Cómo se lee el valor de un parámetro (M9). En la base el valor es texto; aquí se decide su forma
 * canónica, su tipo en PHP y el campo con el que se edita. Agregar un tipo: un caso aquí, en sus
 * match y en el CHECK de la tabla `parametros`.
 */
enum TipoParametro: string
{
    case Texto = 'texto';
    case Entero = 'entero';
    /** Siempre string (bcmath), nunca float. */
    case Decimal = 'decimal';
    case Booleano = 'booleano';
    /** Fecha de negocio Y-m-d. */
    case Fecha = 'fecha';

    /**
     * Campo de acción con el que se edita un parámetro de este tipo: el formulario sale solo.
     */
    public function campo(string $nombre, string $etiqueta): Campo
    {
        return match ($this) {
            self::Texto => Campo::texto($nombre, $etiqueta, maximo: 1000),
            self::Entero => Campo::entero($nombre, $etiqueta),
            self::Decimal => Campo::decimal($nombre, $etiqueta, escala: 6),
            self::Booleano => Campo::booleano($nombre, $etiqueta),
            self::Fecha => Campo::fecha($nombre, $etiqueta),
        };
    }

    /**
     * Valor ya convertido (el que entrega Campo::convertir o el código) → texto que se guarda.
     */
    public function aTexto(mixed $valor): string
    {
        return match ($this) {
            self::Texto, self::Decimal => is_string($valor) ? $valor : throw $this->noCorresponde($valor),
            self::Entero => is_int($valor) ? (string) $valor : throw $this->noCorresponde($valor),
            self::Booleano => is_bool($valor) ? ($valor ? 'true' : 'false') : throw $this->noCorresponde($valor),
            self::Fecha => $valor instanceof CarbonImmutable ? $valor->toDateString() : throw $this->noCorresponde($valor),
        };
    }

    /**
     * Lo que escribe una persona → valor en PHP, o null si no corresponde al tipo. Más tolerante que
     * desdeTexto(): acepta «sí/no» en booleanos y espacios alrededor.
     */
    public function interpretar(string $entrada): string|int|bool|CarbonImmutable|null
    {
        $entrada = trim($entrada);

        return match ($this) {
            self::Texto => $entrada === '' ? null : $entrada,
            self::Entero => preg_match('/^-?\d+$/', $entrada) === 1 ? (int) $entrada : null,
            self::Decimal => preg_match('/^-?\d+(\.\d+)?$/', $entrada) === 1 ? $entrada : null,
            self::Booleano => match (mb_strtolower($entrada)) {
                'true', 'sí', 'si', '1' => true,
                'false', 'no', '0' => false,
                default => null,
            },
            self::Fecha => $this->fechaValida($entrada),
        };
    }

    /**
     * Texto guardado → valor en PHP.
     */
    public function desdeTexto(string $valor): string|int|bool|CarbonImmutable
    {
        return match ($this) {
            self::Texto, self::Decimal => $valor,
            self::Entero => (int) $valor,
            self::Booleano => $valor === 'true',
            self::Fecha => CarbonImmutable::createFromFormat('!Y-m-d', $valor) ?: throw $this->noCorresponde($valor),
        };
    }

    /** Para mensajes: qué se espera que escriba la persona. */
    public function formatoEsperado(): string
    {
        return match ($this) {
            self::Texto => 'un texto',
            self::Entero => 'un número entero',
            self::Decimal => 'un número con punto decimal (por ejemplo, 18.00)',
            self::Booleano => 'sí o no',
            self::Fecha => 'una fecha con el formato AAAA-MM-DD',
        };
    }

    private function fechaValida(string $entrada): ?CarbonImmutable
    {
        try {
            $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $entrada);
        } catch (InvalidFormatException) {
            return null;
        }

        // createFromFormat acepta «2026-02-30» desbordando al mes siguiente: se exige ida y vuelta.
        return $fecha !== null && $fecha->toDateString() === $entrada ? $fecha : null;
    }

    private function noCorresponde(mixed $valor): LogicException
    {
        return new LogicException(sprintf('Un valor %s no corresponde a un parámetro %s.', get_debug_type($valor), $this->value));
    }
}
