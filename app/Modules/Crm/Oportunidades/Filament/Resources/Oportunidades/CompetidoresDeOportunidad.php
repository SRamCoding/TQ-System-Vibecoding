<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades;

use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\Estados\SobreLoPropio;
use App\Modules\Crm\Oportunidades\Filament\PanelesOportunidad;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Models\OportunidadCompetidor;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Resources\Parties\SelectoresParty;
use App\Modules\Nucleo\Models\Moneda;
use Filament\Actions\Action;
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
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pestaña «Competidores» de la oportunidad (plan 2.21, D4 y D11.5): fichas de Personas y empresas con precio opcional y
 * su moneda; el ganador, marcado. Agregar, editar y quitar por las acciones `crm.oportunidades.*-competidor` (todas o
 * las propias, D10), solo mientras está abierta. Cada formulario toma la versión al abrirse.
 */
final class CompetidoresDeOportunidad extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** Fija: el navegador no puede cambiarla para tocar los competidores de otra oportunidad. */
    #[Locked]
    public int $oportunidadId;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(OportunidadesServiceProvider::PERMISO_VER) ?? false, 403);
    }

    #[On('oportunidad-cambiada')]
    public function alCambiarLaOportunidad(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $campos = fn (OportunidadCompetidor $record): array => [
            ...$record->only(['party_id', 'precio', 'moneda_id', 'fortalezas', 'debilidades']),
            'version' => $record->version,
        ];

        return $table
            ->query(fn (): Builder => OportunidadCompetidor::query()->with(['party:id,razon_social'])
                ->where('oportunidad_id', $this->oportunidadId)->orderByDesc('es_ganador')->orderBy('id'))
            ->paginated(false)
            ->columns([
                TextColumn::make('party.razon_social')->label('Empresa')->wrap()
                    ->description(fn (OportunidadCompetidor $record): ?string => $record->es_ganador ? 'Ganó' : null),
                TextColumn::make('precio')->label('Precio')->alignEnd()->placeholder('—')
                    ->state(fn (OportunidadCompetidor $record): ?string => match (true) {
                        $record->precio_desconocido => 'Precio desconocido',
                        $record->precio === null => null,
                        default => PanelesOportunidad::dinero($record->precio, Moneda::query()->whereKey($record->moneda_id)->value('simbolo')),
                    }),
                TextColumn::make('fortalezas')->label('Fortalezas')->wrap()->placeholder('—'),
                TextColumn::make('debilidades')->label('Debilidades')->wrap()->placeholder('—'),
            ])
            ->headerActions([
                $this->guardar('crm.oportunidades.guardar-competidor', fn (): array => ['oportunidad_id' => $this->oportunidadId])
                    ->name('agregar')->label('Agregar competidor')->icon('heroicon-o-plus')
                    ->hidden(fn (): bool => ! $this->abierta()),
                $this->guardar('crm.oportunidades.guardar-competidor-propias', fn (): array => ['oportunidad_id' => $this->oportunidadId])
                    ->name('agregarPropias')->label('Agregar competidor')->icon('heroicon-o-plus')
                    ->hidden(fn (): bool => ! $this->abierta() || OportunidadResource::usuarioPuede(SobreLoPropio::EDITAR) || ! $this->esPropia()),
            ])
            ->recordActions([
                $this->guardar('crm.oportunidades.guardar-competidor', fn (OportunidadCompetidor $record): array => ['oportunidad_id' => $this->oportunidadId, 'competidor_id' => $record->id])
                    ->name('editar')->label('Editar')->icon('heroicon-o-pencil-square')->fillForm($campos)
                    ->hidden(fn (): bool => ! $this->abierta()),
                $this->guardar('crm.oportunidades.guardar-competidor-propias', fn (OportunidadCompetidor $record): array => ['oportunidad_id' => $this->oportunidadId, 'competidor_id' => $record->id])
                    ->name('editarPropias')->label('Editar')->icon('heroicon-o-pencil-square')->fillForm($campos)
                    ->hidden(fn (): bool => ! $this->abierta() || OportunidadResource::usuarioPuede(SobreLoPropio::EDITAR) || ! $this->esPropia()),
                AccionFilament::desde('crm.oportunidades.quitar-competidor', fn (OportunidadCompetidor $record): array => ['competidor_id' => $record->id], $this->avisarGuardado(...))
                    ->name('quitar')->label('Quitar')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->fillForm(fn (OportunidadCompetidor $record): array => ['version' => $record->version])
                    ->hidden(fn (): bool => ! $this->abierta()),
                AccionFilament::desde('crm.oportunidades.quitar-competidor-propias', fn (OportunidadCompetidor $record): array => ['competidor_id' => $record->id], $this->avisarGuardado(...))
                    ->name('quitarPropias')->label('Quitar')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->fillForm(fn (OportunidadCompetidor $record): array => ['version' => $record->version])
                    ->hidden(fn (): bool => ! $this->abierta() || OportunidadResource::usuarioPuede(SobreLoPropio::EDITAR) || ! $this->esPropia()),
            ])
            ->emptyStateHeading('Sin competidores')
            ->emptyStateDescription('Agrega las empresas que compiten por este negocio, con su precio si se conoce.');
    }

    public function render(): View
    {
        return view('oportunidades::tabla');
    }

    private function guardar(string $clave, \Closure $fijos): Action
    {
        return AccionFilament::desde($clave, $fijos, $this->avisarGuardado(...), controles: [
            'party_id' => fn (): Field => SelectoresParty::party('party_id'),
        ]);
    }

    private function oportunidad(): Oportunidad
    {
        return Oportunidad::query()->with('estado')->findOrFail($this->oportunidadId);
    }

    private function abierta(): bool
    {
        return EtapaAbierta::tryFrom($this->oportunidad()->estado->codigo) !== null;
    }

    private function esPropia(): bool
    {
        return OportunidadResource::puedeEditar($this->oportunidad());
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        // El historial de la ficha se vuelve a dibujar con el cambio.
        $this->dispatch('oportunidad-cambiada');
    }
}
