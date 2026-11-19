<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo\Filament\Resources\Catalogo;

use App\Modules\Crm\Catalogo\Filament\Resources\Catalogo\Pages\ListarCatalogo;
use App\Modules\Crm\Catalogo\Filament\Resources\Catalogo\Pages\VerProducto;
use App\Modules\Crm\Catalogo\Filament\Resources\Catalogo\RelationManagers\PreciosRelationManager;
use App\Modules\Crm\Catalogo\Models\Producto;
use App\Modules\Crm\Catalogo\Services\PreciosCatalogoService;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** MVP rápido (ficha t1-catalogo): el catálogo comercial (CRM-06), agrupado por familia, con su ficha. */
final class CatalogoResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = Producto::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Catálogo comercial';

    protected static ?string $modelLabel = 'ítem';

    protected static ?string $pluralModelLabel = 'ítems';

    protected static ?string $slug = 'catalogo';

    public static function canViewAny(): bool
    {
        return self::puede(PreciosCatalogoService::PERMISO_VER);
    }

    public static function canView(Model $record): bool
    {
        return self::puede(PreciosCatalogoService::PERMISO_VER);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function puede(string $permiso): bool
    {
        $u = Filament::auth()->user();

        return $u instanceof User && $u->can($permiso);
    }

    public static function soles(mixed $monto): string
    {
        return $monto === null ? '—' : 'S/ '.number_format((float) $monto, 2);
    }

    /** @return array<string, int> cuántos hay en cada vista, para las etiquetas del filtro */
    public static function cuentas(): array
    {
        $activos = fn (): Builder => Producto::query()->where('es_activo', true);

        return [
            'activos' => $activos()->count(),
            'sin_precio' => $activos()->whereDoesntHave('precioVigente')->count(),
            'sin_cubso' => $activos()->where('es_servicio', false)->whereNull('cubso_id')->count(),
            'descontinuados' => Producto::query()->where('es_activo', false)->count(),
        ];
    }

    public static function table(Table $table): Table
    {
        $c = self::cuentas();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['familia.padre', 'unidadMedida', 'precioVigente'])
                ->leftJoin('familias_producto as f', 'f.id', '=', 'productos.familia_producto_id')->select('productos.*'))
            ->defaultSort('codigo')
            ->groups([
                Group::make('familia_producto_id')->label('Familia')->collapsible()
                    ->getTitleFromRecordUsing(fn (Producto $record): string => $record->familia?->ruta() ?? 'Sin familia')
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderBy('f.codigo', $direction)),
            ])
            ->defaultGroup('familia_producto_id')
            ->columns([
                TextColumn::make('codigo')->label('Código')->fontFamily('mono')->searchable()->sortable(),
                TextColumn::make('descripcion')->label('Descripción')->wrap()->searchable()
                    ->description(fn (Producto $record): ?string => trim(($record->marca ?? '').' '.($record->modelo ?? '')) ?: null),
                TextColumn::make('unidadMedida.abreviatura')->label('Und'),
                TextColumn::make('precio')->label('Precio')->alignEnd()->fontFamily('mono')
                    ->state(fn (Producto $record): string => $record->precioVigente === null ? 'sin precio' : self::soles($record->precioVigente->precio))
                    ->color(fn (Producto $record): ?string => $record->precioVigente === null ? 'danger' : null),
                TextColumn::make('margen')->label('Margen obj.')->alignEnd()
                    ->state(fn (Producto $record): string => $record->precioVigente?->margen_objetivo === null ? '—' : rtrim(rtrim((string) $record->precioVigente->margen_objetivo, '0'), '.').' %'),
                TextColumn::make('cubso')->label('Cód. Estado')->fontFamily('mono')
                    ->state(fn (Producto $record): string => $record->cubso_id === null ? ($record->es_servicio ? '—' : 'falta') : (string) DB::table('cubso')->where('id', $record->cubso_id)->value('codigo'))
                    ->color(fn (Producto $record): ?string => $record->cubso_id === null && ! $record->es_servicio ? 'warning' : null),
            ])
            ->filters([
                SelectFilter::make('vista')->label('Ver')->default('activos')
                    ->options([
                        'activos' => "Activos · {$c['activos']}", 'sin_precio' => "Sin precio vigente · {$c['sin_precio']}",
                        'sin_cubso' => "Sin código del Estado · {$c['sin_cubso']}", 'descontinuados' => "Descontinuados · {$c['descontinuados']}",
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? 'activos') {
                        'sin_precio' => $query->where('productos.es_activo', true)->whereDoesntHave('precioVigente'),
                        'sin_cubso' => $query->where('productos.es_activo', true)->where('es_servicio', false)->whereNull('cubso_id'),
                        'descontinuados' => $query->where('productos.es_activo', false),
                        default => $query->where('productos.es_activo', true),
                    }),
            ])
            ->recordUrl(fn (Producto $record): string => VerProducto::getUrl(['record' => $record]));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('El ítem')->columns(3)->schema([
                TextEntry::make('codigo')->label('Código')->fontFamily('mono')->copyable(),
                TextEntry::make('descripcion')->label('Descripción')->columnSpan(2),
                TextEntry::make('familia')->label('Familia')->state(fn (Producto $record): string => $record->familia?->ruta() ?? 'Sin familia'),
                TextEntry::make('unidadMedida.nombre')->label('Unidad'),
                TextEntry::make('tipo')->label('Tipo')->state(fn (Producto $record): string => $record->es_servicio ? 'Servicio' : ($record->requiere_serie ? 'Bien con número de serie' : 'Bien')),
                TextEntry::make('marca')->label('Marca')->placeholder('—'),
                TextEntry::make('modelo')->label('Modelo')->placeholder('—'),
                TextEntry::make('cubso')->label('Código del Estado (CUBSO)')
                    ->state(fn (Producto $record): string => $record->cubso_id === null ? 'Falta' : (string) DB::table('cubso')->where('id', $record->cubso_id)->selectRaw("codigo || ' · ' || descripcion AS t")->value('t')),
                TextEntry::make('vigente')->label('Precio vigente')->fontFamily('mono')
                    ->state(fn (Producto $record): string => $record->precioVigente === null ? 'Sin precio vigente' : self::soles($record->precioVigente->precio).' desde '.$record->precioVigente->vigente_desde->format('d/m/Y')),
                TextEntry::make('es_activo')->label('Estado')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'Activo' : 'Descontinuado')->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextEntry::make('usos')->label('Lo usan')->state('Cotizaciones, presupuestos, compras y almacén: es el mismo catálogo.'),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [PreciosRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListarCatalogo::route('/'), 'view' => VerProducto::route('/{record}')];
    }
}
