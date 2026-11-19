<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Parties\Pages;

use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Resources\Parties\PartyResource;
use App\Modules\Nucleo\Models\Party;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

/**
 * La ficha de una persona o empresa (plan 1.3, D12): Datos, Roles e Historial. Las acciones de arriba son
 * las registradas `nucleo.parties.*`; cada formulario toma la versión al abrirse (bloqueo optimista).
 *
 * @property-read Party $record
 */
final class VerParty extends ViewRecord
{
    protected static string $resource = PartyResource::class;

    /**
     * Una pestaña (Roles, Contactos, Sedes, Cuentas) guardó un cambio: la ficha se vuelve a dibujar y la pestaña
     * Historial y la línea «Contacto principal» lo muestran sin recargar (plan 1.3b, paso 6).
     */
    #[On('ficha-party-cambiada')]
    public function alCambiarLaFicha(): void
    {
        $this->record->refresh();
    }

    public function getTitle(): string
    {
        return $this->record->razon_social;
    }

    public function getSubheading(): string
    {
        return "{$this->record->tipoDocumento->nombre} {$this->record->numero_documento} · {$this->record->tipo->etiqueta()}"
            .($this->record->es_activo ? '' : ' · Inactiva');
    }

    /**
     * @return list<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->accion('editar', fillForm: fn (Party $record): array => [
                ...$record->only(['razon_social', 'nombres', 'apellidos', 'nombre_comercial', 'direccion_fiscal', 'ubigeo_id', 'telefono', 'email']),
                'version' => $record->version,
            ])
                ->label('Editar')->icon('heroicon-o-pencil-square'),
            $this->accion('editar-tributarios', fillForm: fn (Party $record): array => [
                ...$record->only(['es_agente_retencion', 'es_agente_percepcion', 'es_buen_contribuyente']),
                'version' => $record->version,
            ])
                ->label('Datos tributarios')->icon('heroicon-o-banknotes')->color('warning'),
            // Plan 2.20a, N4: el rubro tiene su propia acción y su propio permiso.
            $this->accion('cambiar-rubro', fillForm: fn (Party $record): array => ['rubro_id' => $record->rubro_id, 'version' => $record->version])
                ->label('Rubro')->icon('heroicon-o-view-columns'),
            ActionGroup::make([
                $this->accion('desactivar')->label('Desactivar')->icon('heroicon-o-eye-slash')->color('gray')->requiresConfirmation()
                    ->modalDescription('Deja de ofrecerse en los selectores; lo ya registrado la conserva.')
                    ->hidden(fn (Party $record): bool => ! $record->es_activo),
                $this->accion('activar')->label('Activar')->icon('heroicon-o-eye')->color('success')->requiresConfirmation()
                    ->hidden(fn (Party $record): bool => $record->es_activo),
                $this->accion('borrar', alTerminar: function (): void {
                    Notification::make()->success()->title('Borrada.')->send();
                    $this->redirect(ListarParties::getUrl());
                })
                    ->label('Borrar')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->modalDescription('Solo se borra lo que nunca se usó, junto con sus roles, contactos, sedes y cuentas. Si ya se usó, desactívala.'),
            ])->label('Más')->icon('heroicon-o-ellipsis-vertical')->button(),
        ];
    }

    /**
     * Una acción registrada sobre esta party: fija su id y llena el formulario al abrir, siempre con la versión
     * leída en ese momento (también en las de solo confirmar: si otro guarda mientras tanto, se rechaza).
     */
    private function accion(string $operacion, ?Closure $fillForm = null, ?Closure $alTerminar = null): Action
    {
        return AccionFilament::desde(
            "nucleo.parties.{$operacion}",
            fn (Party $record): array => ['party_id' => $record->id],
            $alTerminar ?? function (): void {
                Notification::make()->success()->title('Guardado.')->send();
                $this->record->refresh();
                // Para quien la muestra desde afuera: la vista previa del asistente (plan 1.15).
                $this->dispatch('ficha-party-cambiada');
            },
            // En una natural la razón social la arma el sistema; en una jurídica no hay nombres ni apellidos.
            ocultos: fn (Party $record): array => $record->tipo === TipoPersona::Natural ? ['razon_social'] : ['nombres', 'apellidos'],
        )->fillForm($fillForm ?? fn (Party $record): array => ['version' => $record->version]);
    }
}
