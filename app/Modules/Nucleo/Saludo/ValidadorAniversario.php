<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/**
 * El aniversario de Techquk (`interfaz.aniversario_techquk`, plan 1.4b, D7 y D10): la fecha completa como `AAAA-MM-DD`
 * (el año cuenta los años del agradecimiento), o «sin fecha» mientras Gerencia no decida (pendientes.md). Un parámetro
 * de texto no puede quedar vacío: por eso la marca.
 */
final readonly class ValidadorAniversario implements ValidadorParametro
{
    public const string SIN_FECHA = 'sin fecha';

    public function normalizar(mixed $valor): string
    {
        $valor = mb_strtolower(trim((string) $valor));

        return in_array($valor, ['', '-', '--', 'ninguna', self::SIN_FECHA], true) ? self::SIN_FECHA : $valor;
    }

    public function rechazo(mixed $valor): ?string
    {
        return $valor === self::SIN_FECHA || self::fecha((string) $valor) !== null
            ? null
            : 'escribe la fecha como AAAA-MM-DD (por ejemplo, 2010-05-15), o «sin fecha».';
    }

    /**
     * @return ?array{anio: int, mes: int, dia: int} nulo si no es una fecha que exista
     */
    public static function fecha(string $valor): ?array
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $partes) !== 1) {
            return null;
        }

        [$anio, $mes, $dia] = [(int) $partes[1], (int) $partes[2], (int) $partes[3]];

        return $anio >= 1900 && checkdate($mes, $dia, $anio) ? ['anio' => $anio, 'mes' => $mes, 'dia' => $dia] : null;
    }
}
