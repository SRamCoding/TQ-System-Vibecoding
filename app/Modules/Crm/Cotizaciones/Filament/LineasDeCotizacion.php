<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Filament;

use App\Modules\Crm\Catalogo\Services\CatalogoService;
use App\Modules\Crm\Catalogo\Services\ResumenProducto;
use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Models\LineaDeCotizacion;
use App\Modules\Erp\Presupuestos\Services\PresupuestosService;
use App\Modules\Erp\Presupuestos\Services\ResumenPresupuesto;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
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
 * Las líneas de una versión de la cotización (D19), en la pestaña «Líneas» de su ficha: agregar del catálogo (C4), una
 * libre «por catalogar» o las partidas de un presupuesto de la oportunidad (C8); editar y quitar, solo en borrador. Cada
 * acción lleva la versión de la cotización leída al abrir su ventana. Costo y margen solo con su grupo sensible (D15): sin
 * «Costos», la columna no aparece y al editar el costo no se muestra: se conserva el que tiene. Al agregar sí se escribe
 * (no muestra nada guardado).
 */
final class LineasDeCotizacion extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $cotizacionId;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(CotizacionesServiceProvider::PERMISO_VER) ?? false, 403);
    }

    #[On('cotizacion-cambiada')]
    public function alCambiar(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        $editable = fn (): bool => $this->cotizacion()->estado->codigo === DefinicionCotizacion::BORRADOR;
        $version = fn (): array => ['version' => $this->cotizacion()->version];
        $fijo = fn (): array => ['cotizacion_id' => $this->cotizacionId];

        return $table
            ->query(fn (): Builder => LineaDeCotizacion::query()->where('cotizacion_id', $this->cotizacionId))
            ->defaultSort('orden')
            ->paginated(false)
            ->columns([
                TextColumn::make('orden')->label('#')->fontFamily('mono'),
                TextColumn::make('descripcion')->label('Ítem')->wrap()
                    ->description(fn (LineaDeCotizacion $record): ?string => $record->producto_id === null ? 'Por catalogar' : null),
                TextColumn::make('unidad_medida_id')->label('Unidad')
                    ->formatStateUsing(fn (int $state): string => (string) UnidadMedida::query()->whereKey($state)->value('abreviatura')),
                TextColumn::make('cantidad')->label('Cant.')->alignEnd()->fontFamily('mono'),
                TextColumn::make('precio_unitario')->label('P.U. sin IGV')->alignEnd()->fontFamily('mono'),
                TextColumn::make('descuento_porcentaje')->label('Dscto.')->alignEnd()->fontFamily('mono')
                    ->formatStateUsing(fn (string $state): string => bccomp($state, '0', 2) === 0 ? '—' : "{$state} %"),
                TextColumn::make('subtotal')->label('Subtotal')->alignEnd()->fontFamily('mono')
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state, 2)),
                TextColumn::make('costo_unitario')->label('Costo unit.')->alignEnd()->fontFamily('mono')
                    ->visible(fn (): bool => self::ve(GrupoSensible::Costos)),
                TextColumn::make('margen_porcentaje')->label('Margen')->alignEnd()->fontFamily('mono')->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : "{$state} %")
                    ->visible(fn (): bool => self::ve(GrupoSensible::Margen)),
            ])
            ->headerActions([
                AccionFilament::desde('crm.cotizaciones.agregar-linea', $fijo, $this->avisarGuardado(...),
                    ocultos: fn (): array => ['descripcion', 'unidad_medida_id'],
                    controles: ['producto_id' => fn (): Select => self::selectorProducto()])
                    ->name('delCatalogo')->label('Del catálogo')->icon('heroicon-o-squares-plus')->slideOver()
                    ->modalDescription('El producto trae su descripción y su unidad. El precio y el costo van sin IGV.')
                    ->fillForm($version)->hidden(fn (): bool => ! $editable()),
                AccionFilament::desde('crm.cotizaciones.agregar-linea', $fijo, $this->avisarGuardado(...),
                    ocultos: fn (): array => ['producto_id'])
                    ->name('libre')->label('Línea libre')->icon('heroicon-o-pencil')->color('gray')->slideOver()
                    ->modalDescription('Queda «por catalogar»: se puede cotizar algo que todavía no está en el catálogo.')
                    ->fillForm($version)->hidden(fn (): bool => ! $editable()),
                AccionFilament::desde('crm.cotizaciones.enlazar-presupuesto', $fijo, $this->avisarGuardado(...),
                    controles: ['presupuesto_id' => fn (): Select => $this->selectorPresupuesto()])
                    ->name('desdePresupuesto')->label('Desde el presupuesto')->icon('heroicon-o-calculator')->color('gray')->slideOver()
                    ->modalDescription('Trae las partidas del presupuesto de la oportunidad como líneas, con su precio y su costo.')
                    ->fillForm($version)->hidden(fn (): bool => ! $editable()),
            ])
            ->recordActions([
                AccionFilament::desde('crm.cotizaciones.editar-linea',
                    fn (LineaDeCotizacion $record): array => ['linea_id' => $record->id, ...(self::ve(GrupoSensible::Costos) ? [] : ['costo_unitario' => $record->costo_unitario])],
                    $this->avisarGuardado(...))
                    ->name('editarLinea')->label('Editar')->icon('heroicon-m-pencil-square')->slideOver()
                    ->fillForm(fn (LineaDeCotizacion $record): array => [
                        'cantidad' => $record->cantidad,
                        'precio_unitario' => $record->precio_unitario,
                        'descuento_porcentaje' => $record->descuento_porcentaje,
                        ...(self::ve(GrupoSensible::Costos) ? ['costo_unitario' => $record->costo_unitario] : []),
                        ...$version(),
                    ])
                    ->hidden(fn (): bool => ! $editable()),
                AccionFilament::desde('crm.cotizaciones.quitar-linea', fn (LineaDeCotizacion $record): array => ['linea_id' => $record->id], $this->avisarGuardado(...))
                    ->name('quitarLinea')->label('Quitar')->icon('heroicon-m-trash')->color('danger')->requiresConfirmation()
                    ->fillForm($version)->hidden(fn (): bool => ! $editable()),
            ])
            ->emptyStateHeading('Sin líneas')
            ->emptyStateDescription('Agrega productos del catálogo, una línea libre o las partidas del presupuesto.');
    }

    public function render(): View
    {
        return view('cotizaciones::tabla');
    }

    private function cotizacion(): Cotizacion
    {
        return Cotizacion::query()->with('estado')->findOrFail($this->cotizacionId);
    }

    private static function ve(GrupoSensible $grupo): bool
    {
        return Filament::auth()->user()?->can($grupo->permiso()) ?? false;
    }

    /** C4: los productos activos del catálogo, por código o nombre; el elegido se muestra aunque ya no esté activo. */
    private static function selectorProducto(): Select
    {
        $etiqueta = fn (ResumenProducto $p): string => "{$p->codigo} · {$p->nombre} ({$p->unidad})";

        return Select::make('producto_id')->searchable()->required()
            ->getSearchResultsUsing(fn (string $search): array => collect(app(CatalogoService::class)->buscar($search))
                ->mapWithKeys(fn (ResumenProducto $p): array => [$p->id => $etiqueta($p)])->all())
            ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? $etiqueta(app(CatalogoService::class)->resumen((int) $value)) : null);
    }

    /** C8: los presupuestos de la oportunidad que se pueden enlazar (borrador u ofertados), la versión más nueva primero. */
    private function selectorPresupuesto(): Select
    {
        return Select::make('presupuesto_id')->options(fn (): array => collect(app(PresupuestosService::class)->deOportunidad($this->cotizacion()->oportunidad_id))
            ->mapWithKeys(fn (ResumenPresupuesto $p): array => [$p->id => "{$p->numero} v{$p->numeroVersion} · {$p->nombre} ({$p->estadoNombre})"])->all());
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        $this->resetTable();
        $this->dispatch('cotizacion-cambiada');
    }
}
