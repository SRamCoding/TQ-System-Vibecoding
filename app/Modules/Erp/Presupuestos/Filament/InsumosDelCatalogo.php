<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Filament;

use App\Modules\Crm\Catalogo\Services\CatalogoRechazado;
use App\Modules\Crm\Catalogo\Services\CatalogoService;
use App\Modules\Erp\Presupuestos\Models\Insumo;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Nucleo\Filament\AccionFilament;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * El catálogo de insumos (plan 2.10, D2; plan 2.9, D8 y D10), dentro del panel deslizante de Presupuestos: materiales,
 * mano de obra, equipos y subcontratos con su unidad y su precio de referencia; un material puede enlazarse a un
 * producto del catálogo comercial (C4). «Precio sin detallar» es del sistema: se ve, pero no se edita ni se desactiva.
 * Todo cambio va por las acciones `erp.presupuestos.*-insumo`.
 */
final class InsumosDelCatalogo extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can(PresupuestosServiceProvider::PERMISO_VER) ?? false, 403);
    }

    public function table(Table $table): Table
    {
        $campos = fn (Insumo $record): array => [
            ...$record->only(['codigo', 'tipo', 'nombre', 'unidad_medida_id', 'producto_id', 'categoria_costo_hora_id']),
            'precio_referencia' => $record->precio_referencia,
            'version' => $record->version,
        ];

        return $table
            ->query(fn (): Builder => Insumo::query()->with('unidadMedida'))
            ->defaultSort('codigo')
            ->columns([
                TextColumn::make('codigo')->label('Código')->fontFamily('mono')->searchable()->sortable(),
                TextColumn::make('nombre')->label('Nombre')->wrap()->searchable()
                    ->description(fn (Insumo $record): ?string => $record->es_sistema ? 'Del sistema: lo usa el cambio a análisis de precios' : null),
                TextColumn::make('tipo')->label('Tipo')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state): string => SelectoresPresupuesto::TIPOS[$state] ?? $state),
                TextColumn::make('unidadMedida.codigo')->label('Unidad'),
                TextColumn::make('precio_referencia')->label('Precio de referencia')->alignEnd()->fontFamily('mono')->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state === null ? '—' : Montos::siVisible($state)),
                TextColumn::make('producto_id')->label('Producto')->placeholder('—')
                    ->formatStateUsing(fn (?int $state): string => self::producto($state)),
                TextColumn::make('es_activo')->label('Estado')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Activo' : 'Inactivo')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('tipo')->label('Tipo')->options(SelectoresPresupuesto::TIPOS),
                TernaryFilter::make('es_activo')->label('Activos')->default(true),
                TernaryFilter::make('sin_producto')->label('Materiales sin producto')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('tipo', Insumo::MATERIAL)->whereNull('producto_id'),
                        false: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->headerActions([
                AccionFilament::desde('erp.presupuestos.crear-insumo', null, $this->avisarGuardado(...), controles: self::controles())
                    ->name('crearInsumo')->label('Nuevo insumo')->icon('heroicon-o-plus'),
            ])
            ->recordActions([
                AccionFilament::desde('erp.presupuestos.editar-insumo', fn (Insumo $record): array => ['insumo_id' => $record->id], $this->avisarGuardado(...),
                    soloLectura: fn (): array => ['codigo', 'tipo'], controles: self::controles())
                    ->name('editarInsumo')->label('Editar')->icon('heroicon-o-pencil-square')->iconButton()
                    ->fillForm($campos)
                    // El formulario trae el precio de referencia (costos): sin verlo, no se ofrece (ADR 0016).
                    ->hidden(fn (Insumo $record): bool => $record->es_sistema || ! $record->es_activo || ! Montos::visibles()),
                AccionFilament::desde('erp.presupuestos.desactivar-insumo', fn (Insumo $record): array => ['insumo_id' => $record->id], $this->avisarGuardado(...))
                    ->name('desactivarInsumo')->label('Desactivar')->icon('heroicon-o-eye-slash')->color('gray')->iconButton()
                    ->modalDescription('No entra en análisis de precios nuevos; los que ya lo tienen lo siguen mostrando.')
                    ->fillForm(fn (Insumo $record): array => ['version' => $record->version])
                    ->hidden(fn (Insumo $record): bool => $record->es_sistema || ! $record->es_activo),
            ])
            ->emptyStateHeading('Sin insumos')
            ->emptyStateDescription('Agrega el primero con «Nuevo insumo».');
    }

    public function render(): View
    {
        return view('presupuestos::tabla');
    }

    /**
     * @return array<string, \Closure(): Field>
     */
    private static function controles(): array
    {
        return [
            'tipo' => fn (): Field => SelectoresPresupuesto::tipo()->live(),
            'unidad_medida_id' => fn (): Field => SelectoresPresupuesto::unidad()
                ->helperText('Si lo enlazas a un producto, la unidad debe ser la del producto.'),
            // El producto solo se enlaza a un material (D8 de la 2.9).
            'producto_id' => fn (): Field => SelectoresPresupuesto::producto()
                ->visible(fn (Get $get): bool => $get('tipo') === Insumo::MATERIAL)
                ->helperText('Opcional. Sirve para comparar lo presupuestado con lo comprado; se puede completar después.'),
        ];
    }

    private static function producto(?int $productoId): string
    {
        if ($productoId === null) {
            return '—';
        }

        try {
            return app(CatalogoService::class)->resumen($productoId)->codigo;
        } catch (CatalogoRechazado) {
            return "#{$productoId}";
        }
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
    }
}
