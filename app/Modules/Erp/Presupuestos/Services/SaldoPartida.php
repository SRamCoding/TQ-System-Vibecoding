<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/**
 * El saldo de una partida en soles, en el momento de pedirlo (C1; v4, parte 1; plan 2.1, D7). Montos como texto
 * decimal con dos decimales. Disponible = presupuestado − comprometido; negativo es exceso.
 */
final readonly class SaldoPartida
{
    public function __construct(
        public string $presupuestado,
        public string $comprometido,
        public string $ejecutado,
        public string $pagado,
        public string $disponible,
    ) {}

    public function excedida(): bool
    {
        return bccomp($this->disponible, '0', 2) < 0;
    }
}
