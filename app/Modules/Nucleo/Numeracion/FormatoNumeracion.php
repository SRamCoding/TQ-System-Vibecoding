<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Numeracion;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Cómo se numera un tipo de documento (plan 1.6, D3 y D5): la serie (prefijo), cuándo reinicia y cuántos dígitos
 * lleva el correlativo. La declara el módulo en su `DefinicionDocumento::numeracion()`. Al pasar del máximo de dígitos,
 * el número crece (`REQ-2026-10000`): nunca falla.
 */
final readonly class FormatoNumeracion
{
    public function __construct(
        public string $serie,
        public ReinicioNumeracion $reinicio = ReinicioNumeracion::Anual,
        public int $digitos = 4,
    ) {
        if (preg_match('/^[A-Z][A-Z0-9]{0,9}$/', $serie) !== 1) {
            throw new InvalidArgumentException("La serie «{$serie}» debe ir en mayúsculas, sin espacios y con hasta 10 caracteres.");
        }

        if ($digitos < 1 || $digitos > 9) {
            throw new InvalidArgumentException('Los dígitos del correlativo van de 1 a 9.');
        }
    }

    /** El periodo del contador para la fecha del documento, en hora de Lima: `2026`, `2026-10` o vacío. */
    public function periodo(CarbonImmutable $fecha): string
    {
        $lima = $fecha->setTimezone('America/Lima');

        return match ($this->reinicio) {
            ReinicioNumeracion::Anual => $lima->format('Y'),
            ReinicioNumeracion::Mensual => $lima->format('Y-m'),
            ReinicioNumeracion::Nunca => '',
        };
    }

    /**
     * `REQ-2026-0001`, `AS-2026-10-0001`, `COL-0001`, o con ámbito `VAL-CT0003-2026-0001`: el ámbito va dentro del
     * número para que dos contadores de la misma serie nunca den el mismo (D4).
     */
    public function numero(string $periodo, int $correlativo, string $ambito = ''): string
    {
        return implode('-', array_filter([$this->serie, $ambito, $periodo, str_pad((string) $correlativo, $this->digitos, '0', STR_PAD_LEFT)], fn (string $parte): bool => $parte !== ''));
    }
}
