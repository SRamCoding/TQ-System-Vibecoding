<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Parties;

use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Models\PartyContacto;
use App\Modules\Nucleo\Services\PartiesService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
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
 * Pestaña Contactos de la ficha de una party (plan 1.3b, D1 y D2): los vínculos vigentes primero y luego los
 * cerrados, con «viene de» y «ahora en». Avisa si hay vigentes y ninguno es principal. Todo por las acciones
 * registradas `nucleo.parties.*-contacto`; cada formulario toma la versión al abrirse.
 */
final class ContactosDeParty extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** Fija: el navegador no puede cambiarla para leer los contactos de otra party. */
    #[Locked]
    public int $partyId;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can('parties.ver') ?? false, 403);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PartyContacto::query()
                ->with(['anterior.party:id,razon_social', 'siguiente.party:id,razon_social'])
                ->where('party_id', $this->partyId)
                // Vigentes primero (hasta vacío), el principal arriba; luego los cerrados, el más reciente primero.
                ->orderByRaw('hasta IS NOT NULL')->orderByDesc('es_principal')->orderByDesc('hasta')->orderBy('apellidos'))
            ->paginated(false)
            ->heading(fn (): ?string => app(PartiesService::class)->faltaContactoPrincipal($this->partyId) ? 'Sin contacto principal' : null)
            ->description(fn (): ?string => app(PartiesService::class)->faltaContactoPrincipal($this->partyId) ? 'Hay contactos vigentes, pero ninguno es el principal: marca uno.' : null)
            ->columns([
                TextColumn::make('nombres')->label('Nombre')
                    ->formatStateUsing(fn (PartyContacto $record): string => $record->nombreCompleto())
                    ->description(fn (PartyContacto $record): ?string => $record->area),
                TextColumn::make('cargo')->label('Cargo')->placeholder('—'),
                TextColumn::make('telefono')->label('Teléfono')->placeholder('—'),
                TextColumn::make('email')->label('Correo')->placeholder('—'),
                TextColumn::make('marcas')->label('Marcas')->badge()
                    ->state(fn (PartyContacto $record): array => array_values(array_filter([
                        $record->es_principal ? 'Principal' : null,
                        $record->es_decisor ? 'Decisor' : null,
                    ])))
                    ->color(fn (string $state): string => $state === 'Principal' ? 'primary' : 'gray'),
                TextColumn::make('vinculo')->label('Vínculo')->wrap()
                    ->state(fn (PartyContacto $record): string => self::vinculo($record)),
            ])
            ->headerActions([
                AccionFilament::desde('nucleo.parties.agregar-contacto', fn (): array => ['party_id' => $this->partyId], $this->avisarGuardado(...))
                    ->label('Agregar contacto')->icon('heroicon-o-plus'),
            ])
            ->recordActions([
                AccionFilament::desde('nucleo.parties.editar-contacto', fn (PartyContacto $record): array => ['contacto_id' => $record->id], $this->avisarGuardado(...))
                    ->label('Editar')->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (PartyContacto $record): array => [
                        ...$record->only(['nombres', 'apellidos', 'cargo', 'area', 'telefono', 'email', 'es_decisor']),
                        'desde' => $record->desde?->toDateString(),
                        'version' => $record->version,
                    ]),
                ActionGroup::make([
                    AccionFilament::desde('nucleo.parties.marcar-contacto-principal', fn (PartyContacto $record): array => ['contacto_id' => $record->id], $this->avisarGuardado(...))
                        ->label('Marcar como principal')->icon('heroicon-o-star')->requiresConfirmation()
                        ->fillForm(fn (PartyContacto $record): array => ['version' => $record->version])
                        ->hidden(fn (PartyContacto $record): bool => $record->es_principal || $record->hasta !== null),
                    AccionFilament::desde('nucleo.parties.cerrar-vinculo-contacto', fn (PartyContacto $record): array => ['contacto_id' => $record->id], $this->avisarGuardado(...))
                        ->label('Cerrar vínculo')->icon('heroicon-o-stop-circle')->color('gray')
                        ->modalDescription('Ya no está en esta institución. No se borra: deja de ser principal y sale de sus sedes.')
                        ->fillForm(fn (PartyContacto $record): array => ['hasta' => Calendario::hoy(), 'version' => $record->version])
                        ->hidden(fn (PartyContacto $record): bool => $record->hasta !== null),
                    AccionFilament::desde(
                        'nucleo.parties.trasladar-contacto',
                        fn (PartyContacto $record): array => ['contacto_id' => $record->id],
                        $this->avisarGuardado(...),
                        // Un vínculo ya cerrado no pide fecha de cierre.
                        ocultos: fn (PartyContacto $record): array => $record->hasta === null ? [] : ['hasta'],
                        controles: [
                            'nueva_party_id' => fn (): Field => SelectoresParty::party('nueva_party_id', excluir: $this->partyId),
                            'desde' => fn (): Field => DatePicker::make('desde')->format('Y-m-d')
                                ->helperText('Si lo dejas vacío, el día siguiente al cierre.'),
                        ],
                    )
                        ->label('Trasladar a otra institución')->icon('heroicon-o-arrow-right-circle')
                        ->modalDescription('Cierra su vínculo aquí y abre uno nuevo en la otra institución, enlazado. Revisa el correo: el institucional suele cambiar.')
                        // Precarga lo que suele seguir igual; el cargo nuevo se pide (D1).
                        ->fillForm(fn (PartyContacto $record): array => [
                            ...$record->only(['nombres', 'apellidos', 'area', 'telefono', 'email', 'es_decisor']),
                            'hasta' => $record->hasta === null ? Calendario::hoy() : null,
                            'version' => $record->version,
                        ])
                        ->hidden(fn (PartyContacto $record): bool => $record->siguiente !== null),
                    AccionFilament::desde('nucleo.parties.borrar-contacto', fn (PartyContacto $record): array => ['contacto_id' => $record->id], $this->avisarGuardado(...))
                        ->label('Borrar')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                        ->modalDescription('Solo se borra un contacto registrado por error y nunca usado. Si ya no está, cierra su vínculo.')
                        ->fillForm(fn (PartyContacto $record): array => ['version' => $record->version]),
                ])->icon('heroicon-o-ellipsis-vertical'),
            ])
            ->emptyStateHeading('Sin contactos')
            ->emptyStateDescription('Agrega a las personas con quienes se trata: quién decide, quién coordina.');
    }

    public function render(): View
    {
        return view('nucleo::filament.contactos-de-party');
    }

    /** «Vigente · antes en X», «Vínculo cerrado jul 2026 · ahora en Y». */
    private static function vinculo(PartyContacto $contacto): string
    {
        $partes = [$contacto->hasta === null
            ? ($contacto->desde === null ? 'Vigente' : 'Desde '.$contacto->desde->format('d/m/Y'))
            : 'Vínculo cerrado el '.$contacto->hasta->format('d/m/Y')];

        if ($contacto->anterior !== null) {
            $partes[] = 'antes en '.$contacto->anterior->party->razon_social;
        }

        if ($contacto->siguiente !== null) {
            $partes[] = 'ahora en '.$contacto->siguiente->party->razon_social;
        }

        return implode(' · ', $partes);
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        // La pestaña Historial de la ficha se vuelve a dibujar con el cambio (paso 6).
        $this->dispatch('ficha-party-cambiada');
    }
}
