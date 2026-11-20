<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Filament;

use App\Modules\Crm\Cuentas\Pestanas\RegistroPestanasCuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Nucleo\Parties\ComponentePestanaFicha;
use Illuminate\Contracts\View\View;

/**
 * MV-022: cada pestaña de la ficha de la cuenta (Oportunidades, Cotizaciones…) también en la ficha de su persona o
 * empresa. Un solo componente, registrado con un nombre por pestaña (`ficha.cuenta-<clave>`); por su nombre sabe cuál
 * dibujar, con la cuenta de esa ficha.
 */
final class PestanaCuentaEnFicha extends ComponentePestanaFicha
{
    public function render(): View
    {
        $clave = substr($this->getName(), strlen('ficha.cuenta-'));
        $pestana = null;

        foreach (app(RegistroPestanasCuenta::class)->todas() as $p) {
            if ($p->clave === $clave) {
                $pestana = $p;
            }
        }

        return view('cuentas::ficha.pestana-cuenta', [
            'cuenta' => app(CuentasService::class)->deParty($this->partyId),
            'pestana' => $pestana,
        ]);
    }
}
