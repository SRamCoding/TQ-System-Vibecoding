<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Filament\Resources\Cuentas;

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Models\CuentaMarcaCliente;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Nucleo\Filament\TablaHistorial;
use App\Modules\Nucleo\Models\PartyRol;
use App\Modules\Nucleo\Services\HistorialCambiosService;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

/**
 * Historial de la cuenta (plan 2.20, D10): la bitácora del núcleo, que se escribe sola. Junta la cuenta y sus marcas de
 * cliente (también las ya borradas) y, solo para quien ve fichas, los datos de su ficha y sus periodos de «Entidad
 * pública» y «Cliente». Los campos sensibles los quita el núcleo; el historial completo de la ficha está en Personas y
 * empresas.
 */
final class HistorialDeCuenta extends TablaHistorial
{
    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(CuentasServiceProvider::PERMISO_VER) ?? false, 403);
    }

    #[On('cuenta-cambiada')]
    public function alCambiarLaCuenta(): void
    {
        $this->resetPage();
    }

    protected function modelos(): array
    {
        $cuenta = Cuenta::query()->with('party')->findOrFail($this->registroId);
        $historial = app(HistorialCambiosService::class);
        $modelos = [
            $cuenta,
            ...$cuenta->marcasCliente()->get()->all(),
            ...$historial->borradosDe(CuentaMarcaCliente::class, 'cuenta_id', $cuenta->id),
        ];

        if (Filament::auth()->user()?->can('parties.ver') ?? false) {
            $tipos = fn (Builder $q): Builder => $q->whereIn('codigo', [CuentasService::ROL_ENTIDAD, CuentasService::ROL_CLIENTE]);
            array_push(
                $modelos,
                $cuenta->party,
                ...PartyRol::query()->where('party_id', $cuenta->party_id)->whereHas('rol', $tipos)->with('rol')->get()->all(),
            );
        }

        return $modelos;
    }
}
