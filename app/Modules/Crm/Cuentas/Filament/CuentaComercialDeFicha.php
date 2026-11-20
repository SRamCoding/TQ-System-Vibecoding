<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Filament;

use App\Modules\Crm\Actividades\Services\ActividadesService;
use App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\CuentaResource;
use App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\Pages\NuevaCuenta;
use App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\Pages\VerCuenta;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Parties\ComponentePestanaFicha;
use Illuminate\Contracts\View\View;

/**
 * Pestaña «Cuenta comercial» de la ficha de una empresa con RUC (plan 2.20, D13; MV-022 le suma la actividad y el
 * histórico de precios de la entidad): si es cuenta del CRM, quién la atiende, si es cliente o prospecto, pública o privada y si está inactiva; si no, «Crear cuenta» con el RUC puesto. El
 * alta vuelve a comprobar todo al guardar.
 */
final class CuentaComercialDeFicha extends ComponentePestanaFicha
{
    public function render(): View
    {
        $cuenta = app(CuentasService::class)->deParty($this->partyId);
        $modelo = $cuenta === null ? null : Cuenta::query()->with(['nivelGobierno', 'motivoInactiva'])->find($cuenta->id);
        $ficha = Party::query()->find($this->partyId, ['id', 'numero_documento', 'es_activo']);

        return view('cuentas::ficha.cuenta-comercial', [
            'cuenta' => $cuenta,
            'tipo' => $modelo === null ? null : CuentaResource::tipo($modelo),
            'motivo' => $modelo?->motivoInactiva?->nombre,
            'urlCuenta' => $cuenta === null ? null : VerCuenta::getUrl(['record' => $cuenta->id]),
            'urlCrear' => $cuenta === null && $ficha?->es_activo === true && CuentaResource::usuarioPuede('crm.cuentas.crear')
                ? NuevaCuenta::getUrl(['ruc' => $ficha->numero_documento])
                : null,
            'fichaActiva' => $ficha?->es_activo === true,
            // MV-022: lo que tenía la ficha de la cuenta: su actividad y el histórico de precios de la entidad.
            'actividades' => $cuenta === null ? [] : app(ActividadesService::class)->deCuenta($cuenta->id),
            'historico' => $cuenta === null ? null : app(LicitacionesService::class)->historico($cuenta->id),
        ]);
    }
}
