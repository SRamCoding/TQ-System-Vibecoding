<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Imputacion;

/**
 * El saldo de una partida con una o más líneas incluidas, en soles (plan 2.2, D3; v4, parte 1: «comprometido con esta
 * línea»). Montos como texto decimal. Disponible después = presupuestado − (comprometido + líneas); negativo es exceso.
 */
final readonly class SaldoConLinea
{
    public function __construct(
        public int $partidaId,
        /** «PC-01.02 Cable», para los mensajes. */
        public string $partida,
        public string $presupuestado,
        public string $comprometido,
        public string $lineas,
    ) {}

    public function comprometidoConLineas(): string
    {
        return bcadd($this->comprometido, $this->lineas, 2);
    }

    public function disponibleDespues(): string
    {
        return bcsub($this->presupuestado, $this->comprometidoConLineas(), 2);
    }

    public function excede(): bool
    {
        return bccomp($this->disponibleDespues(), '0', 2) < 0;
    }

    public function excedeEn(): string
    {
        return $this->excede() ? bcmul($this->disponibleDespues(), '-1', 2) : '0.00';
    }

    /** Lo usado sobre lo presupuestado, en porcentaje con dos decimales; sin presupuestado, nulo. */
    public function porcentajeDespues(): ?string
    {
        return self::porcentaje($this->comprometidoConLineas(), $this->presupuestado);
    }

    /** Lo usado antes de estas líneas. */
    public function porcentajeAntes(): ?string
    {
        return self::porcentaje($this->comprometido, $this->presupuestado);
    }

    private static function porcentaje(string $usado, string $presupuestado): ?string
    {
        return bccomp($presupuestado, '0', 2) === 0 ? null : bcdiv(bcmul($usado, '100', 4), $presupuestado, 2);
    }
}
