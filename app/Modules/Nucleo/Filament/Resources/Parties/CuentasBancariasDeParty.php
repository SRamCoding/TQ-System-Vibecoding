<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Parties;

use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Models\PartyCuentaBancaria;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pestaña Cuentas bancarias de la ficha de una party (plan 1.3b, D5). Solo la abre quien tiene
 * `campos.ver-datos_bancarios` (plan 1.4, D6): el componente lo revisa en el servidor, no solo la pestaña. Banco, número y
 * moneda no se editan: si cambian, se desactiva y se registra otra. Todo por las acciones registradas.
 */
final class CuentasBancariasDeParty extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** Fija: el navegador no puede cambiarla para leer las cuentas de otra party. */
    #[Locked]
    public int $partyId;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(GrupoSensible::DatosBancarios->permiso()) ?? false, 403);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PartyCuentaBancaria::query()->with(['banco', 'moneda'])->where('party_id', $this->partyId)
                ->orderByDesc('es_activa')->orderByDesc('es_principal')->orderBy('es_detraccion')->orderBy('id'))
            ->paginated(false)
            ->columns([
                TextColumn::make('banco.razon_social')->label('Banco')
                    ->formatStateUsing(fn (PartyCuentaBancaria $record): string => $record->banco->nombre_comercial ?? $record->banco->razon_social)
                    ->description(fn (PartyCuentaBancaria $record): ?string => $record->es_activa ? null : 'Desactivada'),
                TextColumn::make('numero_cuenta')->label('Número')->fontFamily('mono')->copyable(),
                TextColumn::make('cci')->label('CCI')->fontFamily('mono')->placeholder('—')->copyable(),
                TextColumn::make('moneda.codigo')->label('Moneda'),
                TextColumn::make('marcas')->label('Marcas')->badge()
                    ->state(fn (PartyCuentaBancaria $record): array => array_values(array_filter([
                        $record->es_principal ? 'Principal' : null,
                        $record->es_detraccion ? 'Detracciones' : null,
                    ])))
                    ->color(fn (string $state): string => $state === 'Principal' ? 'primary' : 'warning'),
            ])
            ->headerActions([
                AccionFilament::desde(
                    'nucleo.parties.registrar-cuenta-bancaria',
                    fn (): array => ['party_id' => $this->partyId],
                    $this->avisarGuardado(...),
                    controles: ['banco_party_id' => fn (): Field => SelectoresParty::party('banco_party_id', 'banco')],
                )
                    ->label('Registrar cuenta')->icon('heroicon-o-plus')
                    ->modalDescription('Banco, número y moneda no se podrán cambiar: si cambian, se desactiva esta cuenta y se registra otra.'),
            ])
            ->recordActions([
                AccionFilament::desde('nucleo.parties.completar-cci', fn (PartyCuentaBancaria $record): array => ['cuenta_id' => $record->id], $this->avisarGuardado(...))
                    ->label('Completar CCI')->icon('heroicon-o-pencil-square')
                    ->modalDescription('Un CCI registrado ya no cambia.')
                    ->fillForm(fn (PartyCuentaBancaria $record): array => ['version' => $record->version])
                    ->hidden(fn (PartyCuentaBancaria $record): bool => $record->cci !== null),
                ActionGroup::make([
                    AccionFilament::desde('nucleo.parties.marcar-cuenta-principal', fn (PartyCuentaBancaria $record): array => ['cuenta_id' => $record->id], $this->avisarGuardado(...))
                        ->label('Marcar como principal')->icon('heroicon-o-star')->requiresConfirmation()
                        ->modalDescription('Será la cuenta por defecto para pagar en su moneda.')
                        ->fillForm(fn (PartyCuentaBancaria $record): array => ['version' => $record->version])
                        ->hidden(fn (PartyCuentaBancaria $record): bool => $record->es_principal || $record->es_detraccion || ! $record->es_activa),
                    AccionFilament::desde('nucleo.parties.desactivar-cuenta-bancaria', fn (PartyCuentaBancaria $record): array => ['cuenta_id' => $record->id], $this->avisarGuardado(...))
                        ->label('Desactivar')->icon('heroicon-o-eye-slash')->color('gray')->requiresConfirmation()
                        ->modalDescription('Deja de usarse para pagar o cobrar; lo ya registrado la conserva.')
                        ->fillForm(fn (PartyCuentaBancaria $record): array => ['version' => $record->version])
                        ->hidden(fn (PartyCuentaBancaria $record): bool => ! $record->es_activa),
                    AccionFilament::desde('nucleo.parties.activar-cuenta-bancaria', fn (PartyCuentaBancaria $record): array => ['cuenta_id' => $record->id], $this->avisarGuardado(...))
                        ->label('Activar')->icon('heroicon-o-eye')->color('warning')->requiresConfirmation()
                        ->modalDescription('Vuelve a recibir pagos. Confirma antes con la persona o empresa que la cuenta sigue siendo suya.')
                        ->fillForm(fn (PartyCuentaBancaria $record): array => ['version' => $record->version])
                        ->hidden(fn (PartyCuentaBancaria $record): bool => $record->es_activa),
                    AccionFilament::desde('nucleo.parties.borrar-cuenta-bancaria', fn (PartyCuentaBancaria $record): array => ['cuenta_id' => $record->id], $this->avisarGuardado(...))
                        ->label('Borrar')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                        ->modalDescription('Solo se borra una cuenta registrada por error y nunca usada. Si ya se usó, desactívala.')
                        ->fillForm(fn (PartyCuentaBancaria $record): array => ['version' => $record->version]),
                ])->icon('heroicon-o-ellipsis-vertical'),
            ])
            ->emptyStateHeading('Sin cuentas bancarias')
            ->emptyStateDescription('Registra las cuentas donde se le paga o desde donde paga.');
    }

    public function render(): View
    {
        return view('nucleo::filament.cuentas-bancarias-de-party');
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        $this->dispatch('ficha-party-cambiada');
    }
}
