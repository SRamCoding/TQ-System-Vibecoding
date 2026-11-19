<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Filament;

use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\ConsultaPresupuestos;
use App\Modules\Erp\Presupuestos\Services\FilaPresupuesto;
use App\Modules\Nucleo\Filament\AccionFilament;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * La pestaña «Partidas» de la ficha del presupuesto (plan 2.10, D4): títulos y partidas en orden de código, con sangría
 * por nivel; la partida con unidad, metrado, P.U. y parcial, y el título con la suma de sus partidas. En borrador se
 * agregan, editan y quitan por las acciones registradas; el APU de cada partida se abre en el panel deslizante (paso 6).
 */
final class PartidasDePresupuesto extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $presupuestoId;

    /** D4: la partida cuyo APU está abierto en el panel; `?apu=` lo abre directo (un enlace guardado). */
    #[Url(as: 'apu')]
    public ?int $apu = null;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(PresupuestosServiceProvider::PERMISO_VER) ?? false, 403);
        $this->abrirApu($this->apu);
    }

    /** Abre el APU de una partida de este presupuesto (o lo cierra con null), sin recargar. */
    public function abrirApu(?int $partidaId): void
    {
        $this->apu = $partidaId !== null && PartidaDePresupuesto::query()->whereKey($partidaId)->where('presupuesto_id', $this->presupuestoId)->exists()
            ? $partidaId
            : null;
    }

    public function cerrarApu(): void
    {
        $this->apu = null;
    }

    #[On('presupuesto-cambiado')]
    public function alCambiar(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $presupuesto = Presupuesto::query()->with('estado')->findOrFail($this->presupuestoId);
        $enBorrador = $presupuesto->estado->codigo === DefinicionPresupuesto::BORRADOR;
        $esTitulo = fn (array $record): bool => (bool) $record['es_titulo'];

        return $table
            ->records(fn (): array => $this->filas())
            ->paginated(false)
            // D4: presionar una partida abre su análisis de precios en el panel deslizante.
            ->recordAction(fn (array $record): ?string => $esTitulo($record) || ! $presupuesto->usa_apu ? null : 'verApu')
            ->columns([
                TextColumn::make('codigo')->label('Código')->fontFamily('mono')
                    ->weight(fn (array $record): ?string => $esTitulo($record) ? 'bold' : null),
                TextColumn::make('descripcion')->label('Descripción')->wrap()
                    ->extraAttributes(fn (array $record): array => ['style' => 'padding-inline-start: '.($record['nivel'] * 1.25).'rem'])
                    ->weight(fn (array $record): ?string => $esTitulo($record) ? 'bold' : null)
                    ->description(fn (array $record): ?string => ! $esTitulo($record) && $presupuesto->usa_apu && $record['lineas_apu'] === 0 ? 'Sin análisis de precios' : null),
                TextColumn::make('unidad')->label('Unidad')->placeholder(''),
                TextColumn::make('metrado')->label('Metrado')->alignEnd()->fontFamily('mono')->placeholder('')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '' : Montos::texto($state)),
                TextColumn::make('precio_unitario')->label('P.U.')->alignEnd()->fontFamily('mono')->placeholder('')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '' : Montos::siVisible(bcadd($state, '0', $presupuesto->decimales_precio_unitario))),
                TextColumn::make('parcial')->label('Parcial')->alignEnd()->fontFamily('mono')
                    ->weight(fn (array $record): ?string => $esTitulo($record) ? 'bold' : null)
                    ->formatStateUsing(fn (string $state): string => Montos::siVisible($state)),
            ])
            ->headerActions([
                $this->agregarTitulo(null)->name('agregarTitulo')->label('Agregar título')->icon('heroicon-o-plus')
                    ->hidden(! $enBorrador),
            ])
            ->recordActions([
                Action::make('verApu')->label('Ver APU')->icon('heroicon-o-beaker')->iconButton()
                    ->action(fn (array $record) => $this->abrirApu((int) $record['id']))
                    ->hidden(fn (array $record): bool => $esTitulo($record) || ! $presupuesto->usa_apu),
                ActionGroup::make([
                    $this->agregarTitulo(fn (array $record): int => (int) $record['id'])->name('agregarSubtitulo')->label('Agregar subtítulo')
                        ->icon('heroicon-o-folder-plus')
                        ->hidden(fn (array $record): bool => ! $enBorrador || ! $esTitulo($record)),
                    $this->agregarPartida($presupuesto)
                        ->hidden(fn (array $record): bool => ! $enBorrador || ! $esTitulo($record)),
                    AccionFilament::desde('erp.presupuestos.quitar-titulo-presupuesto', fn (array $record): array => ['titulo_id' => (int) $record['id']], $this->avisarGuardado(...))
                        ->name('quitarTitulo')->label('Quitar título')->icon('heroicon-o-trash')->color('danger')
                        ->modalDescription('Solo un título sin títulos ni partidas debajo.')
                        ->fillForm(fn (array $record): array => ['version' => (int) $record['version']])
                        ->hidden(fn (array $record): bool => ! $enBorrador || ! $esTitulo($record)),
                    // Sin APU, el formulario trae el P.U. (costos): sin verlo, no se ofrece (ADR 0016).
                    $this->editarPartida($presupuesto)
                        ->hidden(fn (array $record): bool => ! $enBorrador || $esTitulo($record) || (! $presupuesto->usa_apu && ! Montos::visibles())),
                    AccionFilament::desde('erp.presupuestos.quitar-partida-presupuesto', fn (array $record): array => ['partida_id' => (int) $record['id']], $this->avisarGuardado(...))
                        ->name('quitarPartida')->label('Quitar partida')->icon('heroicon-o-trash')->color('danger')
                        ->modalDescription('Se quita con su análisis de precios.')
                        ->fillForm(fn (array $record): array => ['version' => (int) $record['version']])
                        ->hidden(fn (array $record): bool => ! $enBorrador || $esTitulo($record)),
                ])->hidden(! $enBorrador),
            ])
            ->emptyStateHeading('Sin títulos ni partidas')
            ->emptyStateDescription($enBorrador ? 'Empieza con «Agregar título»; las partidas van debajo de un título.' : null);
    }

    public function render(): View
    {
        return view('presupuestos::partidas');
    }

    /**
     * @param  ?\Closure(array<string, mixed>): int  $padre
     */
    private function agregarTitulo(?\Closure $padre): Action
    {
        $padreDe = fn (mixed $record): ?int => $padre === null || ! is_array($record) ? null : $padre($record);

        return AccionFilament::desde(
            'erp.presupuestos.agregar-titulo-presupuesto',
            fn (mixed $record = null): array => ['presupuesto_id' => $this->presupuestoId, 'padre_id' => $padreDe($record)],
            $this->avisarGuardado(...),
        )->fillForm(fn (mixed $record = null): array => ['codigo' => app(ConsultaPresupuestos::class)->codigoSiguiente($this->presupuestoId, $padreDe($record))]);
    }

    private function agregarPartida(Presupuesto $presupuesto): Action
    {
        // D9 de la 2.9: con APU el P.U. sale del análisis; sin APU se escribe. El rendimiento solo tiene sentido con APU.
        $ocultos = fn (): array => $presupuesto->usa_apu ? ['precio_unitario'] : ['rendimiento', 'unidad_rendimiento'];

        return AccionFilament::desde(
            'erp.presupuestos.agregar-partida-presupuesto',
            fn (array $record): array => ['presupuesto_id' => $this->presupuestoId, 'titulo_id' => (int) $record['id']],
            $this->avisarGuardado(...),
            ocultos: $ocultos,
            controles: self::controlesPartida(),
        )
            ->name('agregarPartida')->label('Agregar partida')->icon('heroicon-o-plus')->slideOver()
            ->fillForm(fn (array $record): array => ['codigo' => app(ConsultaPresupuestos::class)->codigoSiguiente($this->presupuestoId, (int) $record['id'])]);
    }

    private function editarPartida(Presupuesto $presupuesto): Action
    {
        $ocultos = fn (): array => $presupuesto->usa_apu ? ['precio_unitario'] : ['rendimiento', 'unidad_rendimiento'];

        return AccionFilament::desde(
            'erp.presupuestos.editar-partida-presupuesto',
            fn (array $record): array => ['partida_id' => (int) $record['id']],
            $this->avisarGuardado(...),
            ocultos: $ocultos,
            controles: self::controlesPartida(),
        )
            ->name('editarPartida')->label('Editar partida')->icon('heroicon-o-pencil-square')->slideOver()
            ->fillForm(fn (array $record): array => [
                'descripcion' => $record['descripcion'],
                'unidad_medida_id' => $record['unidad_medida_id'],
                'metrado' => $record['metrado'],
                'precio_unitario' => $presupuesto->usa_apu ? null : $record['precio_unitario'],
                'rendimiento' => $record['rendimiento'],
                'unidad_rendimiento' => $record['unidad_rendimiento'],
                'version' => (int) $record['version'],
            ]);
    }

    /**
     * @return array<string, \Closure(): Field>
     */
    private static function controlesPartida(): array
    {
        return [
            'unidad_medida_id' => fn (): Field => SelectoresPresupuesto::unidad(),
            'unidad_rendimiento' => fn (): Field => Select::make('unidad_rendimiento')->options(['hora' => 'Por hora', 'jornada' => 'Por jornada'])
                ->helperText('Lo usan las líneas de mano de obra y equipo que no llevan uno propio.'),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function filas(): array
    {
        $partidas = PartidaDePresupuesto::query()
            ->where('presupuesto_id', $this->presupuestoId)
            ->get(['id', 'unidad_medida_id', 'rendimiento', 'unidad_rendimiento'])
            ->keyBy('id');
        $filas = [];

        foreach (app(ConsultaPresupuestos::class)->filas($this->presupuestoId) as $fila) {
            $partida = $fila->esTitulo ? null : $partidas[$fila->id];
            $filas[($fila->esTitulo ? 't' : 'p').$fila->id] = [
                ...self::fila($fila),
                'unidad_medida_id' => $partida?->unidad_medida_id,
                'rendimiento' => $partida?->rendimiento,
                'unidad_rendimiento' => $partida?->unidad_rendimiento,
            ];
        }

        return $filas;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fila(FilaPresupuesto $fila): array
    {
        return [
            'es_titulo' => $fila->esTitulo, 'id' => $fila->id, 'codigo' => $fila->codigo, 'descripcion' => $fila->descripcion,
            'nivel' => $fila->nivel, 'unidad' => $fila->unidad, 'metrado' => $fila->metrado, 'precio_unitario' => $fila->precioUnitario,
            'parcial' => $fila->parcial, 'lineas_apu' => $fila->lineasApu, 'version' => $fila->version,
        ];
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        $this->resetTable();
        $this->dispatch('presupuesto-cambiado');
    }
}
