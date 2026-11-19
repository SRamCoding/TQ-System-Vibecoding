<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Enums;

/** Los cuatro casos del primer paso de «Nueva cuenta» (plan 2.20, D2). */
enum CasoRuc: string
{
    /** La ficha existe y ya tiene cuenta: no se crea nada. */
    case CuentaExiste = 'cuenta_existe';
    /** La ficha existe, activa y sin cuenta: se pide solo lo comercial. */
    case FichaSinCuenta = 'ficha_sin_cuenta';
    /** La ficha existe e inactiva: se revisa y reactiva antes en Personas y empresas. */
    case FichaInactiva = 'ficha_inactiva';
    /** No hay ficha: se crea con la cuenta, con la razón social (o nombres y apellidos, en un RUC 10). */
    case FichaNueva = 'ficha_nueva';
}
