<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Enums;

/**
 * Qué clase de día es una fila del catálogo de feriados (plan 1.4d, D2). Solo cuenta como no hábil lo que Techquk no
 * trabaja: un feriado nacional obliga al sector privado y un día libre de la empresa lo da la empresa, así que los dos
 * siempre; un día no laborable decretado por el gobierno no obliga al sector privado, solo si la empresa lo da.
 */
enum TipoFeriado: string
{
    case FeriadoNacional = 'feriado_nacional';
    case NoLaborable = 'no_laborable';
    case LibreEmpresa = 'libre_empresa';

    public function etiqueta(): string
    {
        return match ($this) {
            self::FeriadoNacional => 'Feriado nacional',
            self::NoLaborable => 'Día no laborable (decretado por el gobierno)',
            self::LibreEmpresa => 'Día libre de la empresa',
        };
    }

    /** Si Techquk no trabaja ese día sin que nadie lo marque. */
    public function nuncaSeTrabaja(): bool
    {
        return $this !== self::NoLaborable;
    }
}
