<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Impedimentos;

/** El registro de impedimentos para perder y retroceder (plan 2.21, D12.3): cada módulo suma los suyos. */
final class ImpedimentosDeOportunidad
{
    /** @var list<ImpedimentoDeOportunidad> */
    private array $impedimentos = [];

    public function registrar(ImpedimentoDeOportunidad $impedimento): void
    {
        $this->impedimentos[] = $impedimento;
    }

    /** @return list<string> lo que impide la transición, como lo nombra el mensaje: «la cotización COT-… (Aceptada)» */
    public function para(int $oportunidadId, string $transicion): array
    {
        $impiden = [];

        foreach ($this->impedimentos as $impedimento) {
            if (in_array($transicion, $impedimento->transiciones(), true)) {
                foreach ($impedimento->impiden($oportunidadId) as $documento) {
                    $impiden[] = $documento->descripcion();
                }
            }
        }

        return $impiden;
    }
}
