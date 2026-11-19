<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Organigrama;

use App\Modules\Nucleo\Enums\TipoUnidadOrganizativa;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Clusters\Seguridad;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\Resources\Organigrama\Pages\ListarUnidades;
use App\Modules\Nucleo\Filament\Resources\Organigrama\Pages\VerUnidad;
use App\Modules\Nucleo\Filament\SelectoresSeguridad;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\OrganigramaService;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Organigrama (plan 1.4, D5 y D10): las unidades en árbol, cada una con su puesto de jefatura y quién lo ocupa, o
 * «vacante». Los puestos, en la ficha de cada unidad. Todo cambio pasa por las acciones `nucleo.organigrama.*`.
 */
final class UnidadOrganizativaResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = UnidadOrganizativa::class;

    protected static ?string $cluster = Seguridad::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Organigrama';

    protected static ?string $modelLabel = 'unidad';

    protected static ?string $pluralModelLabel = 'unidades';

    protected static ?string $slug = 'organigrama';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->can('organigrama.ver');
    }

    public static function canView(Model $record): bool
    {
        return self::canViewAny();
    }

    // Solo lectura: los cambios pasan por acciones registradas.
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

    /**
     * @return Builder<UnidadOrganizativa>
     */
    public static function getEloquentQuery(): Builder
    {
        return UnidadOrganizativa::query()->enArbol()->with(['jefePuesto', 'padre'])->withCount(['puestos' => fn (Builder $q): Builder => $q->where('es_activo', true)]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                // La sangría muestra de quién depende cada unidad; el orden lo da el árbol, no se reordena.
                TextColumn::make('nombre')->label('Unidad')->searchable()
                    ->formatStateUsing(fn (string $state, UnidadOrganizativa $record): string => str_repeat('— ', (int) $record->getAttribute('nivel')).$state)
                    ->description(fn (UnidadOrganizativa $record): string => $record->tipo->etiqueta()),
                TextColumn::make('codigo')->label('Código')->fontFamily('mono')->searchable(),
                TextColumn::make('jefatura')->label('Jefatura')
                    ->state(fn (UnidadOrganizativa $record): string => $record->jefePuesto->nombre ?? 'Sin definir')
                    ->description(fn (UnidadOrganizativa $record): ?string => self::ocupanteDeLaJefatura($record)),
                TextColumn::make('puestos_count')->label('Puestos activos'),
                IconColumn::make('es_activa')->label('Activa')->boolean(),
            ])
            ->recordActions(self::accionesDeUnidad())
            ->emptyStateHeading('Organigrama vacío')
            ->emptyStateDescription('El organigrama real lo define Gerencia. Empieza por la unidad de más arriba.');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('ficha')->columnSpanFull()->tabs([
                Tab::make('Datos')->icon('heroicon-o-identification')->schema([
                    Section::make('Unidad')->columns(3)->schema([
                        TextEntry::make('nombre')->label('Nombre'),
                        TextEntry::make('codigo')->label('Código')->fontFamily('mono'),
                        TextEntry::make('tipo')->label('Tipo')->formatStateUsing(fn (TipoUnidadOrganizativa $state): string => $state->etiqueta()),
                        TextEntry::make('padre.nombre')->label('Depende de')->placeholder('Nadie: es la de más arriba'),
                        TextEntry::make('jefePuesto.nombre')->label('Puesto de jefatura')->placeholder('Sin definir'),
                        TextEntry::make('jefe')->label('Jefe')
                            ->state(fn (UnidadOrganizativa $record): string => self::ocupanteDeLaJefatura($record) ?? '—'),
                        IconEntry::make('es_activa')->label('Activa')->boolean(),
                    ]),
                ]),
                Tab::make('Puestos')->icon('heroicon-o-briefcase')->schema([
                    Livewire::make(PuestosDeUnidad::class, fn (UnidadOrganizativa $record): array => ['unidadId' => $record->id])->key('puestos-de-unidad'),
                ]),
            ]),
        ]);
    }

    /**
     * Editar, designar jefatura, desactivar y activar: en cada fila y arriba de la ficha, con la versión tomada al
     * abrir.
     *
     * @param  ?Closure(): void  $alTerminar
     * @return list<Action>
     */
    public static function accionesDeUnidad(?Closure $alTerminar = null): array
    {
        $aviso = function () use ($alTerminar): void {
            Notification::make()->success()->title('Guardado.')->send();

            if ($alTerminar !== null) {
                $alTerminar();
            }
        };
        $fijos = fn (UnidadOrganizativa $record): array => ['unidad_id' => $record->id];

        return [
            AccionFilament::desde('nucleo.organigrama.editar-unidad', $fijos, $aviso, controles: ['superior_id' => fn () => SelectoresSeguridad::unidad('superior_id')])
                ->label('Editar')->icon('heroicon-o-pencil-square')
                ->fillForm(fn (UnidadOrganizativa $record): array => [
                    'codigo' => $record->codigo, 'nombre' => $record->nombre, 'tipo' => $record->tipo->value,
                    'superior_id' => $record->parent_id, 'orden' => $record->orden, 'version' => $record->version,
                ]),
            AccionFilament::desde('nucleo.organigrama.designar-jefatura', $fijos, $aviso, controles: ['puesto_id' => fn () => SelectoresSeguridad::puestoDeLaUnidad('puesto_id')])
                ->label('Designar jefatura')->icon('heroicon-o-star')
                ->modalDescription('Un puesto de la misma unidad, de una sola persona. Vacío deja la jefatura sin definir.')
                ->fillForm(fn (UnidadOrganizativa $record): array => ['puesto_id' => $record->jefe_puesto_id, 'version' => $record->version]),
            AccionFilament::desde('nucleo.organigrama.desactivar-unidad', $fijos, $aviso)
                ->label('Desactivar')->icon('heroicon-o-no-symbol')->color('danger')
                ->modalDescription('Solo si ya no tiene unidades ni puestos activos.')
                ->fillForm(fn (UnidadOrganizativa $record): array => ['version' => $record->version])
                ->hidden(fn (UnidadOrganizativa $record): bool => ! $record->es_activa),
            AccionFilament::desde('nucleo.organigrama.activar-unidad', $fijos, $aviso)
                ->label('Activar')->icon('heroicon-o-check-circle')->color('success')
                ->fillForm(fn (UnidadOrganizativa $record): array => ['version' => $record->version])
                ->hidden(fn (UnidadOrganizativa $record): bool => $record->es_activa),
        ];
    }

    /** «Ana Pérez», «Vacante» si nadie activo ocupa el puesto de jefatura, o nulo si no está definido. */
    public static function ocupanteDeLaJefatura(UnidadOrganizativa $unidad): ?string
    {
        if ($unidad->jefe_puesto_id === null) {
            return null;
        }

        return app(OrganigramaService::class)->jefeDe($unidad)->name ?? 'Vacante';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarUnidades::route('/'),
            'view' => VerUnidad::route('/{record}'),
        ];
    }
}
