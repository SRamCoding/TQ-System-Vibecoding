<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Filament\Resources\Proyectos;

use App\Modules\Erp\Proyectos\Filament\Portada\DibujoProyecto;
use App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\Pages\ListarProyectos;
use App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\Pages\VerProyecto;
use App\Modules\Erp\Proyectos\Filament\ResumenDeProyecto;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Pestanas\PestanaProyecto;
use App\Modules\Erp\Proyectos\Pestanas\RegistroPestanasProyecto;
use App\Modules\Erp\Proyectos\ProyectosServiceProvider;
use App\Modules\Erp\Proyectos\Services\FotoProyectoService;
use App\Modules\Erp\Proyectos\Services\PortadaProyectos;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\PestanaHistorial;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * «Proyectos» (plan 2.10, D1 y D3): lista con portada y ficha con Datos, las pestañas que suman otros módulos
 * (Presupuesto, Partidas de control) e Historial. Solo presentación: todo cambio pasa por las acciones registradas.
 */
final class ProyectoResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = Proyecto::class;

    protected static string|BackedEnum|null $navigationIcon = ProyectosServiceProvider::ICONO;

    protected static ?string $navigationLabel = 'Proyectos';

    protected static ?string $modelLabel = 'proyecto';

    protected static ?string $pluralModelLabel = 'proyectos';

    protected static ?string $slug = 'proyectos';

    public static function canViewAny(): bool
    {
        return self::usuarioPuede(ProyectosServiceProvider::PERMISO_VER);
    }

    public static function canView(Model $record): bool
    {
        return self::usuarioPuede(ProyectosServiceProvider::PERMISO_VER);
    }

    // El alta es el panel «Nuevo proyecto interno» y los cambios van por acciones registradas.
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function usuario(): ?User
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }

    public static function usuarioPuede(string $permiso): bool
    {
        return self::usuario()?->can($permiso) ?? false;
    }

    public static function cliente(Proyecto $proyecto): string
    {
        return $proyecto->es_interno ? 'Interno' : (string) Party::query()->whereKey($proyecto->party_id)->value('razon_social');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['estado', 'jefe:id,name,es_activo'])
                ->select('proyectos.*')
                ->selectSub(Party::query()->select('razon_social')->whereColumn('parties.id', 'proyectos.party_id')->limit(1), 'cliente_nombre'))
            ->defaultSort('codigo', 'desc')
            ->columns([
                TextColumn::make('codigo')->label('Código')->fontFamily('mono')->searchable()->sortable(),
                TextColumn::make('nombre')->label('Nombre')->wrap()->searchable()
                    ->description(fn (Proyecto $record): string => $record->es_interno ? 'Interno' : (string) $record->getAttribute('cliente_nombre')),
                TextColumn::make('jefe.name')->label('Jefe de proyecto')
                    ->description(fn (Proyecto $record): ?string => $record->jefe->es_activo ? null : 'Inactivo'),
                // El color va con el nombre del estado (interfaz.md, «Reglas»).
                TextColumn::make('estado_id')->label('Estado')->badge()
                    ->formatStateUsing(fn (Proyecto $record): string => $record->estado->nombre)
                    ->color(fn (Proyecto $record): string => $record->estado->colorEnPantalla()),
                TextColumn::make('fecha_inicio')->label('Inicio')->date('d/m/Y')->sortable(),
                TextColumn::make('fecha_fin_prevista')->label('Fin previsto')->date('d/m/Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('revisar')->label('Revisar')
                    ->options(['jefe-inactivo' => 'Con jefe inactivo'])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? app(PortadaProyectos::class)->enAviso($query, (string) $data['value']) : $query),
                TernaryFilter::make('es_interno')->label('Interno o de contrato')->trueLabel('Internos')->falseLabel('De contrato'),
                SelectFilter::make('jefe_proyecto_user_id')->label('Jefe de proyecto')->relationship('jefe', 'name')->searchable(),
            ])
            ->recordUrl(fn (Proyecto $record): string => VerProyecto::getUrl(['record' => $record]))
            ->emptyStateHeading('Sin proyectos');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            // Plan 2.61, D5: la cabecera con la foto de fondo.
            View::make('proyectos::ficha.cabecera')->columnSpanFull()->viewData(fn (Proyecto $record): array => ['cabecera' => self::cabecera($record)]),
            Tabs::make('ficha')->columnSpanFull()->tabs([
                // Plan 2.61, D5: el Resumen va primero.
                Tab::make('Resumen')->icon('heroicon-o-presentation-chart-bar')->schema([
                    Livewire::make(ResumenDeProyecto::class, fn (Proyecto $record): array => ['proyectoId' => $record->id])->key('proyecto-resumen'),
                ]),
                Tab::make('Datos')->icon('heroicon-o-identification')->schema([
                    Section::make('El proyecto')->columns(3)->schema([
                        TextEntry::make('codigo')->label('Código')->fontFamily('mono')->copyable(),
                        TextEntry::make('nombre')->label('Nombre')->columnSpan(2),
                        TextEntry::make('cliente')->label('Cliente')->state(fn (Proyecto $record): string => self::cliente($record)),
                        TextEntry::make('jefe.name')->label('Jefe de proyecto'),
                        TextEntry::make('origen')->label('Origen')
                            ->state(fn (Proyecto $record): string => $record->contrato_id === null ? 'Proyecto interno' : "Contrato #{$record->contrato_id}"),
                        TextEntry::make('fecha_inicio')->label('Inicio')->date('d/m/Y'),
                        TextEntry::make('fecha_fin_prevista')->label('Fin previsto')->date('d/m/Y'),
                        TextEntry::make('estado.nombre')->label('Estado')->badge()
                            ->color(fn (Proyecto $record): string => $record->estado->colorEnPantalla()),
                    ]),
                ]),
                // D3: las que suman otros módulos (Presupuesto, Partidas de control…), cada una con su permiso.
                ...array_map(fn (PestanaProyecto $pestana): Tab => Tab::make($pestana->titulo)->icon($pestana->icono)
                    ->visible(fn (): bool => app(RegistroPestanasProyecto::class)->puedeVer(self::usuario(), $pestana->permiso))
                    ->schema([
                        Livewire::make($pestana->nombreLivewire(), fn (Proyecto $record): array => ['proyectoId' => $record->id])->key('proyecto-'.$pestana->clave),
                    ]), app(RegistroPestanasProyecto::class)->pestanasPara(self::usuario())),
                PestanaHistorial::make(HistorialDeProyecto::class, fn (Proyecto $record): int => $record->id),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarProyectos::route('/'),
            'view' => VerProyecto::route('/{record}'),
        ];
    }

    /** @return array<string, mixed> lo que dibuja la cabecera de la ficha (plan 2.61, D5) */
    public static function cabecera(Proyecto $proyecto): array
    {
        return [
            'codigo' => $proyecto->codigo,
            'nombre' => $proyecto->nombre,
            'cliente' => self::cliente($proyecto),
            'estado' => $proyecto->estado->nombre,
            'color_estado' => $proyecto->estado->colorEnPantalla(),
            'jefe' => $proyecto->jefe->name,
            'inicio' => $proyecto->fecha_inicio->format('d/m/Y'),
            'fin' => $proyecto->fecha_fin_prevista->format('d/m/Y'),
            'foto' => FotoProyectoService::url($proyecto),
            'dibujo' => DibujoProyecto::svg($proyecto->id),
        ];
    }
}
