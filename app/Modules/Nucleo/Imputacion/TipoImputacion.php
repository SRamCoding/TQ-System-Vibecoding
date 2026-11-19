<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Imputacion;

/** Las tres opciones del selector de imputación (v4, parte 1; plan 2.2, D2). */
enum TipoImputacion: string
{
    /** Proyecto › partida de control. */
    case Partida = 'partida';

    /** Gasto directo sin proyecto: un centro de costo. */
    case CentroDeCosto = 'centro_costo';

    /** Gasto directo con proyecto: la PC-99, un gasto no presupuestado (plan 2.6). */
    case NoPresupuestado = 'no_presupuestado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Partida => 'Proyecto › partida',
            self::CentroDeCosto => 'Gasto directo sin proyecto',
            self::NoPresupuestado => 'Gasto directo con proyecto',
        };
    }
}
