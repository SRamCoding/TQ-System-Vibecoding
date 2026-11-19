<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Respaldos;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;

/**
 * `respaldos.ultima_prueba_restauracion` (plan 1.12, D3): la fecha de la última prueba de restauración aprobada
 * (`docs/operaciones/restauracion.md`) como AAAA-MM-DD, o «sin probar». Nunca una fecha futura. Un parámetro de texto no
 * puede quedar vacío: por eso la marca.
 */
final readonly class ValidadorPruebaRestauracion implements ValidadorParametro
{
    public const string SIN_PROBAR = 'sin probar';

    public function normalizar(mixed $valor): string
    {
        $valor = mb_strtolower(trim((string) $valor));

        return in_array($valor, ['', '-', 'ninguna', self::SIN_PROBAR], true) ? self::SIN_PROBAR : $valor;
    }

    public function rechazo(mixed $valor): ?string
    {
        if ($valor === self::SIN_PROBAR) {
            return null;
        }

        $fecha = self::fecha((string) $valor);

        return $fecha !== null && $fecha->lte(Calendario::ahora()->startOfDay())
            ? null
            : 'escribe la fecha de la prueba como AAAA-MM-DD (hoy o antes), o «sin probar».';
    }

    public static function fecha(string $valor): ?CarbonImmutable
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $partes) !== 1 || ! checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $valor, 'America/Lima') ?: null;
    }
}
