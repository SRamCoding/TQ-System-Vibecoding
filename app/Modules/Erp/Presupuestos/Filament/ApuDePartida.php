<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Filament;

use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\Models\LineaApu;
use App\Modules\Erp\Presupuestos\Models\PartidaDePresupuesto;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\InsumosService;
use App\Modules\Nucleo\Filament\AccionFilament;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
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
 * El análisis de precios unitarios de una partida (plan 2.10, D4; plan 2.9, D4, D5 y D12), en el panel que se desliza
 * desde la derecha sobre la pestaña «Partidas». Cada insumo con lo escrito (cantidad y desperdicio, o rendimiento,
 * cuadrilla y de dónde sale el rendimiento), la cantidad efectiva, el precio y el parcial; arriba, el P.U. que suman.
 * En borrador se agregan, editan y quitan líneas por las acciones registradas; el precio vacío toma el sugerido.
 */
final class ApuDePartida extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $partidaId;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(PresupuestosServiceProvider::PERMISO_VER) ?? false, 403);
    }

    public function table(Table $table): Table
    {
        $partida = $this->partida();
        $enBorrador = $partida->presupuesto->estado->codigo === DefinicionPresupuesto::BORRADOR && $partida->presupuesto->usa_apu;

        return $table
            ->query(fn (): Builder => LineaApu::query()->with('insumo.unidadMedida')->where('presupuesto_partida_id', $this->partidaId))
            ->defaultSort('id')
            ->paginated(false)
            ->heading("{$partida->codigo} · {$partida->descripcion}")
            ->description(self::resumen($partida))
            ->columns([
                TextColumn::make('insumo.codigo')->label('Insumo')->fontFamily('mono')
                    ->description(fn (LineaApu $record): string => $record->insumo->nombre),
                TextColumn::make('insumo.tipo')->label('Tipo')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state): string => SelectoresPresupuesto::TIPOS[$state] ?? $state),
                TextColumn::make('insumo.unidadMedida.codigo')->label('Unidad'),
                TextColumn::make('cantidad')->label('Cantidad')->alignEnd()->fontFamily('mono')
                    ->description(fn (LineaApu $record): string => self::escrito($record, $partida)),
                TextColumn::make('precio')->label('Precio')->alignEnd()->fontFamily('mono')
                    ->formatStateUsing(fn (string $state): string => Montos::siVisible($state)),
                TextColumn::make('parcial')->label('Parcial')->alignEnd()->fontFamily('mono')
                    ->formatStateUsing(fn (string $state): string => Montos::siVisible($state)),
            ])
            ->headerActions([
                AccionFilament::desde('erp.presupuestos.agregar-insumo-apu', fn (): array => ['partida_id' => $this->partidaId], $this->avisarGuardado(...), controles: self::controles())
                    ->name('agregarInsumo')->label('Agregar insumo')->icon('heroicon-o-plus')
                    ->fillForm(fn (): array => ['version' => $this->partida()->version, 'cuadrilla' => '1', 'desperdicio_pct' => '0'])
                    ->hidden(! $enBorrador),
            ])
            ->recordActions([
                AccionFilament::desde('erp.presupuestos.editar-insumo-apu', fn (LineaApu $record): array => ['linea_id' => $record->id], $this->avisarGuardado(...), soloLectura: fn (): array => ['insumo_id'], controles: self::controles())
                    ->name('editarInsumo')->label('Editar')->icon('heroicon-o-pencil-square')->iconButton()
                    ->fillForm(fn (LineaApu $record): array => [
                        'insumo_id' => $record->insumo_id,
                        'cantidad_neta' => $record->cantidad_neta,
                        'desperdicio_pct' => $record->desperdicio_pct,
                        // D12 de la 2.9: solo el propio; vacío, la línea usa el de la partida.
                        'rendimiento' => $record->rendimiento_propio ? $record->rendimiento : null,
                        'unidad_rendimiento' => $record->rendimiento_propio ? $record->unidad_rendimiento : null,
                        'cuadrilla' => $record->cuadrilla,
                        'precio' => $record->precio,
                        'version' => $record->version,
                    ])
                    // El formulario trae el precio de la línea (costos): sin verlo, no se ofrece (ADR 0016).
                    ->hidden(! $enBorrador || ! Montos::visibles()),
                AccionFilament::desde('erp.presupuestos.quitar-insumo-apu', fn (LineaApu $record): array => ['linea_id' => $record->id], $this->avisarGuardado(...))
                    ->name('quitarInsumo')->label('Quitar')->icon('heroicon-o-trash')->color('danger')->iconButton()
                    ->fillForm(fn (LineaApu $record): array => ['version' => $record->version])
                    ->hidden(! $enBorrador),
            ])
            ->emptyStateHeading('Sin insumos')
            ->emptyStateDescription($enBorrador ? 'Agrega el primero con «Agregar insumo»: su suma es el P.U. de la partida.' : null);
    }

    public function render(): View
    {
        return view('presupuestos::tabla');
    }

    private function partida(): PartidaDePresupuesto
    {
        return PartidaDePresupuesto::query()->with(['presupuesto.estado', 'unidadMedida'])->findOrFail($this->partidaId);
    }

    /** «Metrado 600.000 m · P.U. 4.02 · rendimiento de la partida 20 m por hora». */
    private static function resumen(PartidaDePresupuesto $partida): string
    {
        $unidad = $partida->unidadMedida->codigo;

        return implode(' · ', [
            "Metrado {$partida->metrado} {$unidad}",
            'P.U. '.Montos::siVisible(bcadd($partida->costo_unitario, '0', $partida->presupuesto->decimales_precio_unitario)),
            $partida->rendimiento === null ? 'sin rendimiento de la partida' : 'rendimiento de la partida '.self::rendimiento($partida->rendimiento, $partida->unidad_rendimiento, $unidad),
        ]);
    }

    /** Lo que se escribió en la línea y de dónde sale la cantidad efectiva. */
    private static function escrito(LineaApu $linea, PartidaDePresupuesto $partida): string
    {
        if (! $linea->insumo->vaPorRendimiento()) {
            return 'Neta '.$linea->cantidad_neta.(bccomp($linea->desperdicio_pct, '0', 2) === 0 ? '' : " + {$linea->desperdicio_pct} % de desperdicio");
        }

        $rendimiento = self::rendimiento((string) $linea->rendimiento, $linea->unidad_rendimiento, $partida->unidadMedida->codigo);

        return "Cuadrilla {$linea->cuadrilla} · ".($linea->rendimiento_propio ? "rendimiento propio {$rendimiento}" : "rendimiento de la partida ({$rendimiento})");
    }

    private static function rendimiento(string $valor, ?string $unidad, string $unidadPartida): string
    {
        return rtrim(rtrim($valor, '0'), '.')." {$unidadPartida} por ".($unidad === 'jornada' ? 'jornada' : 'hora');
    }

    /**
     * Los campos según el tipo de insumo (D4 de la 2.9): cantidad y desperdicio para material y subcontrato;
     * rendimiento propio, su unidad y cuadrilla para mano de obra y equipo. El precio vacío toma el sugerido.
     *
     * @return array<string, Closure(): Field>
     */
    private static function controles(): array
    {
        $porRendimiento = fn (Get $get): bool => (bool) Insumo::query()->whereKey((int) $get('insumo_id'))->first()?->vaPorRendimiento();
        $elegido = fn (Get $get): bool => filled($get('insumo_id'));

        return [
            'insumo_id' => fn (): Field => Select::make('insumo_id')->searchable()->live()
                ->getSearchResultsUsing(fn (string $search): array => Insumo::query()->where('es_activo', true)->where('es_sistema', false)
                    ->where(fn (Builder $q): Builder => $q->where('codigo', 'ilike', "%{$search}%")->orWhere('nombre', 'ilike', "%{$search}%"))
                    ->orderBy('codigo')->limit(20)->get()
                    ->mapWithKeys(fn (Insumo $i): array => [$i->id => "{$i->codigo} · {$i->nombre}"])->all())
                ->getOptionLabelUsing(fn (mixed $value): ?string => ($i = Insumo::query()->find((int) $value)) === null ? null : "{$i->codigo} · {$i->nombre}"),
            'cantidad_neta' => fn (): Field => TextInput::make('cantidad_neta')->numeric()
                ->visible(fn (Get $get): bool => $elegido($get) && ! $porRendimiento($get)),
            'desperdicio_pct' => fn (): Field => TextInput::make('desperdicio_pct')->numeric()->suffix('%')
                ->visible(fn (Get $get): bool => $elegido($get) && ! $porRendimiento($get)),
            'rendimiento' => fn (): Field => TextInput::make('rendimiento')->numeric()
                ->helperText('Vacío: usa el de la partida. Escríbelo solo si este insumo rinde distinto (por ejemplo, un equipo que se usa una parte del tiempo).')
                ->visible(fn (Get $get): bool => $elegido($get) && $porRendimiento($get)),
            'unidad_rendimiento' => fn (): Field => Select::make('unidad_rendimiento')->options(['hora' => 'Por hora', 'jornada' => 'Por jornada'])
                ->visible(fn (Get $get): bool => $elegido($get) && $porRendimiento($get)),
            'cuadrilla' => fn (): Field => TextInput::make('cuadrilla')->numeric()
                ->helperText('Cuántas personas o equipos de este insumo.')
                ->visible(fn (Get $get): bool => $elegido($get) && $porRendimiento($get)),
            'precio' => fn (): Field => TextInput::make('precio')->numeric()
                ->helperText(function (Get $get): string {
                    $insumo = Insumo::query()->find((int) $get('insumo_id'));
                    $sugerido = $insumo === null ? null : app(InsumosService::class)->precioInicial($insumo, CarbonImmutable::now('America/Lima'));

                    return $sugerido === null || ! Montos::visibles()
                        ? 'Escribe el precio.'
                        : 'Vacío: '.Montos::texto($sugerido).' (el sugerido o el de referencia). Se guarda como foto en la línea.';
                })
                ->visible(fn (Get $get): bool => $elegido($get)),
        ];
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        $this->resetTable();
        $this->dispatch('presupuesto-cambiado');
    }
}
