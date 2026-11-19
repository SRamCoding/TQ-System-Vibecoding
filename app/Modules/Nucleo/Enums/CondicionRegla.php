<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * Cuándo entra una regla de aprobación, además de su rango de monto (plan 2.2, D5). Es una lista: cada condición nueva
 * se suma como un valor más, sin otra columna.
 */
enum CondicionRegla: string
{
    case Ninguna = 'ninguna';

    /** Solo si alguna línea del documento excede su partida (`imputaciones.excede_partida`). */
    case ExcesoDePartida = 'exceso_partida';

    /** Solo si alguna línea del documento va a la PC-99: un gasto no presupuestado (plan 2.6, D2). */
    case GastoNoPresupuestado = 'gasto_no_presupuestado';

    /** Solo si el margen del documento queda bajo el mínimo (plan 2.22, D14: la cotización; lo dice su definición). */
    case MargenBajoElMinimo = 'margen_bajo_minimo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ninguna => 'Siempre (según el monto)',
            self::ExcesoDePartida => 'Solo si alguna línea excede su partida',
            self::GastoNoPresupuestado => 'Solo si alguna línea va a la PC-99',
            self::MargenBajoElMinimo => 'Solo si el margen queda bajo el mínimo',
        };
    }

    /**
     * Si una regla con esta condición admite tope de autoaprobación. El exceso de partida (plan 2.2), el gasto no
     * presupuestado (plan 2.6, D2) y el margen bajo el mínimo (plan 2.22, D14) los firma siempre otra persona; la base
     * lo exige igual.
     */
    public function admiteAutoaprobacion(): bool
    {
        return $this === self::Ninguna;
    }
}
