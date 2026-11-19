<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\Pages;

use App\Modules\Crm\Cuentas\Enums\OrigenMarcaCliente;
use App\Modules\Crm\Cuentas\Filament\Resources\Cuentas\CuentaResource;
use App\Modules\Crm\Cuentas\Filament\SelectoresCuenta;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Pestanas\BotonCuenta;
use App\Modules\Crm\Cuentas\Pestanas\RegistroPestanasCuenta;
use App\Modules\Crm\Cuentas\Services\CuentaDesactivada;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Nucleo\Filament\AccionFilament;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Attributes\On;

/**
 * La ficha de una cuenta (plan 2.20, D11.3): cabecera con razón social, RUC, cliente o prospecto, pública o privada,
 * ejecutivo y sus marcas; botones según permiso (Editar, Reasignar, Marcar cliente o Quitar marca, Desactivar o
 * Reactivar, Rubro) y los que suman otros módulos (D12). Cada formulario toma la versión al abrirse.
 *
 * @property-read Cuenta $record
 */
final class VerCuenta extends ViewRecord
{
    protected static string $resource = CuentaResource::class;

    #[On('cuenta-cambiada')]
    public function alCambiarLaCuenta(): void
    {
        $this->record->refresh();
    }

    public function getTitle(): string
    {
        return $this->record->party->razon_social;
    }

    public function getSubheading(): string
    {
        $cuenta = $this->record;
        $partes = [
            "RUC {$cuenta->party->numero_documento}",
            $cuenta->cliente_desde === null ? 'Prospecto' : 'Cliente desde '.$cuenta->cliente_desde->format('d/m/Y'),
            CuentaResource::tipo($cuenta),
            "Ejecutivo: {$cuenta->ejecutivo->name}",
            app(CuentasService::class)->motivoNoUsableDe($cuenta)?->etiqueta(),
        ];

        return implode(' · ', array_filter($partes));
    }

    /**
     * @return list<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        $campos = fn (Cuenta $record): array => [...$record->only(['origen_comercial_id', 'origen_detalle', 'nivel_gobierno_id', 'notas']), 'version' => $record->version];
        // D4: el nivel solo se pide en una pública.
        $sinNivelSiPrivada = fn (Cuenta $record): array => str_starts_with(CuentaResource::tipo($record), 'Pública') ? [] : ['nivel_gobierno_id'];

        return [
            $this->accion('editar', ocultos: $sinNivelSiPrivada)->label('Editar')->icon('heroicon-o-pencil-square')->fillForm($campos)
                ->hidden(fn (Cuenta $record): bool => ! $record->es_activa),
            $this->accion('editar-propias', ocultos: $sinNivelSiPrivada)->label('Editar')->icon('heroicon-o-pencil-square')->fillForm($campos)
                ->hidden(fn (Cuenta $record): bool => ! $record->es_activa || CuentaResource::usuarioPuede('crm.cuentas.editar') || ! CuentaResource::puedeEditar($record)),
            $this->accion('reasignar', fijos: fn (Cuenta $record): array => ['cuenta_ids' => [$record->id]], controles: ['ejecutivo_user_id' => fn (): Field => SelectoresCuenta::ejecutivo()])
                ->label('Reasignar')->icon('heroicon-o-arrows-right-left')
                ->fillForm(fn (Cuenta $record): array => ['version' => $record->version, 'incluir_oportunidades_abiertas' => true]),
            // D5: el rubro es de la ficha, y se cambia por el núcleo con su propio permiso.
            AccionFilament::desde('nucleo.parties.cambiar-rubro', fn (Cuenta $record): array => ['party_id' => $record->party_id], $this->avisarGuardado(...))
                ->label('Rubro')->icon('heroicon-o-view-columns')
                ->fillForm(fn (Cuenta $record): array => ['rubro_id' => $record->party->rubro_id, 'version' => $record->party->version]),
            ...array_map(fn (BotonCuenta $boton): Action => $boton->accion($this->record->id), app(RegistroPestanasCuenta::class)->botonesPara(CuentaResource::usuario())),
            ActionGroup::make([
                $this->accion('marcar-cliente')->label('Marcar como cliente')->icon('heroicon-o-check-badge')
                    ->modalDescription('Para los clientes de antes del sistema. Si después se gana una oportunidad más antigua, vale la fecha más antigua.')
                    ->fillForm(fn (Cuenta $record): array => ['version' => $record->version])
                    ->hidden(fn (Cuenta $record): bool => ! $record->es_activa || $record->marcasCliente()->vigentes()->where('origen', OrigenMarcaCliente::Manual)->exists()),
                $this->accion('quitar-marca-cliente')->label('Quitar la marca de cliente')->icon('heroicon-o-x-circle')->color('danger')
                    ->modalDescription('Solo la puesta a mano, y si la cuenta no tiene oportunidades ganadas. La cuenta vuelve a prospecto.')
                    ->fillForm(fn (Cuenta $record): array => ['version' => $record->version])
                    ->hidden(fn (Cuenta $record): bool => ! $record->es_activa || ! $record->marcasCliente()->vigentes()->where('origen', OrigenMarcaCliente::Manual)->exists()),
                $this->accionDesactivar(),
                $this->accion('reactivar')->label('Reactivar')->icon('heroicon-o-eye')->color('success')->requiresConfirmation()
                    ->fillForm(fn (Cuenta $record): array => ['version' => $record->version])
                    ->hidden(fn (Cuenta $record): bool => $record->es_activa),
            ])->label('Más')->icon('heroicon-o-ellipsis-vertical')->button(),
        ];
    }

    /** D9: el motivo de la lista (selector común del núcleo, con su detalle con «Otro»); con «Creada por error», también la ficha. */
    private function accionDesactivar(): Action
    {
        return $this->accion('desactivar', controles: [
            'desactivar_ficha' => fn (): Field => Toggle::make('desactivar_ficha')
                ->helperText('Solo si la ficha no tiene otros tipos que «Cliente» y puedes desactivar fichas; si no, se te avisa.')
                ->visible(fn (Get $get): bool => SelectoresCuenta::esCreadaPorError($get('motivo_id'))),
        ], alTerminar: function (mixed $resultado): void {
            $aviso = $resultado instanceof CuentaDesactivada ? $resultado->avisoFicha : null;
            $notificacion = Notification::make()->success()->title('Cuenta desactivada.')->body($aviso);
            // Un aviso sobre la ficha se queda hasta que la persona lo cierre.
            ($aviso === null ? $notificacion : $notificacion->persistent())->send();
            $this->refrescar();
        })
            ->label('Desactivar')->icon('heroicon-o-eye-slash')->color('gray')
            ->modalDescription('Nunca se borra. Deja de ofrecerse para trabajo nuevo; lo ya registrado la conserva.')
            ->fillForm(fn (Cuenta $record): array => ['version' => $record->version])
            ->hidden(fn (Cuenta $record): bool => ! $record->es_activa);
    }

    /**
     * Una acción registrada `crm.cuentas.<operacion>` sobre esta cuenta.
     *
     * @param  array<string, Closure(): Field>  $controles
     */
    private function accion(string $operacion, ?Closure $fijos = null, array $controles = [], ?Closure $ocultos = null, ?Closure $alTerminar = null): Action
    {
        return AccionFilament::desde(
            "crm.cuentas.{$operacion}",
            $fijos ?? fn (Cuenta $record): array => ['cuenta_id' => $record->id],
            $alTerminar ?? $this->avisarGuardado(...),
            ocultos: $ocultos,
            controles: $controles,
        );
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        $this->refrescar();
    }

    private function refrescar(): void
    {
        $this->record->refresh();
        $this->record->party->refresh();
        $this->dispatch('cuenta-cambiada');
    }
}
