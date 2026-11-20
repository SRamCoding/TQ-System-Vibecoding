<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Usuarios;

use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\Clusters\Seguridad;
use App\Modules\Nucleo\Filament\EtiquetasEnEspanol;
use App\Modules\Nucleo\Filament\Resources\Usuarios\Pages\ListarUsuarios;
use App\Modules\Nucleo\Filament\Resources\Usuarios\Pages\VerUsuario;
use App\Modules\Nucleo\Filament\SelectoresSeguridad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use BackedEnum;
use Carbon\CarbonImmutable;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Usuarios (plan 1.4, D10): cada cuenta es una persona, con su puesto, sus roles globales y por unidad, y su último
 * acceso. Solo presentación: todo cambio pasa por las acciones `nucleo.seguridad.*`. La cuenta de sistema no aparece.
 */
final class UsuarioResource extends Resource
{
    use EtiquetasEnEspanol;

    protected static ?string $model = User::class;

    protected static ?string $cluster = Seguridad::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static ?string $slug = 'usuarios';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return self::usuarioPuede('usuarios.ver');
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
     * @return Builder<User>
     */
    public static function getEloquentQuery(): Builder
    {
        return User::query()->where('es_sistema', false)->with(['puesto.unidad', 'roles']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable()
                    ->description(fn (User $record): string => $record->email),
                TextColumn::make('email')->label('Correo')->searchable()->hidden(),
                TextColumn::make('puesto.nombre')->label('Puesto')->placeholder('Sin puesto')
                    ->description(fn (User $record): ?string => $record->puesto?->unidad->nombre),
                TextColumn::make('roles_globales')->label('Roles')->badge()->placeholder('Sin roles')
                    ->state(fn (User $record): array => $record->roles->pluck('name')->sort()->values()->all()),
                IconColumn::make('es_activo')->label('Activo')->boolean(),
                TextColumn::make('ultimo_acceso_at')->label('Último acceso')->dateTime('d/m/Y H:i', Calendario::ZONA)
                    ->placeholder('Nunca')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('es_activo')->label('Activo'),
            ])
            ->recordActions(self::accionesDeCuenta());
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('ficha')->columnSpanFull()->tabs([
                Tab::make('Datos')->icon('heroicon-o-identification')->schema([
                    Section::make('Cuenta')->columns(3)->schema([
                        TextEntry::make('name')->label('Nombre'),
                        TextEntry::make('email')->label('Correo')->copyable(),
                        TextEntry::make('documento')->label('Documento')
                            ->state(fn (User $record): string => $record->party === null ? '—' : "{$record->party->tipoDocumento->nombre} {$record->party->numero_documento}"),
                        TextEntry::make('puesto.nombre')->label('Puesto')->placeholder('Sin puesto'),
                        TextEntry::make('puesto.unidad.nombre')->label('Unidad')->placeholder('—'),
                        IconEntry::make('es_activo')->label('Activa')->boolean(),
                        TextEntry::make('ultimo_acceso_at')->label('Último acceso')->dateTime('d/m/Y H:i', Calendario::ZONA)->placeholder('Nunca'),
                        // Plan 1.4b, D6: sin año.
                        TextEntry::make('cumpleanos')->label('Cumpleaños')->placeholder('Sin registrar')
                            ->state(fn (User $record): ?string => $record->cumpleanos_dia === null || $record->cumpleanos_mes === null ? null
                                : CarbonImmutable::create(2024, $record->cumpleanos_mes, $record->cumpleanos_dia)?->locale('es')->translatedFormat('j \\d\\e F')),
                    ]),
                ]),
                Tab::make('Roles')->icon('heroicon-o-key')->schema([
                    Livewire::make(RolesDeUsuario::class, fn (User $record): array => ['usuarioId' => $record->id])->key('roles-de-usuario'),
                ]),
                Tab::make('Roles por unidad')->icon('heroicon-o-building-office-2')->schema([
                    Livewire::make(RolesPorUnidadDeUsuario::class, fn (User $record): array => ['usuarioId' => $record->id])->key('roles-por-unidad-de-usuario'),
                ]),
            ]),
        ]);
    }

    /**
     * Cambiar puesto, desactivar y activar: en cada fila de la lista y arriba de la ficha. La versión se captura al
     * abrir el formulario.
     *
     * @param  ?Closure(): void  $alTerminar
     * @return list<Action>
     */
    public static function accionesDeCuenta(?Closure $alTerminar = null): array
    {
        $aviso = function () use ($alTerminar): void {
            Notification::make()->success()->title('Guardado.')->send();

            if ($alTerminar !== null) {
                $alTerminar();
            }
        };

        return [
            AccionFilament::desde('nucleo.seguridad.cambiar-puesto-usuario', fn (User $record): array => ['usuario_id' => $record->id], $aviso,
                controles: ['puesto_id' => fn () => SelectoresSeguridad::puesto('puesto_id')])
                ->label('Cambiar puesto')->icon('heroicon-o-briefcase')
                ->modalDescription('Vacío lo deja sin puesto. Un puesto de jefatura es de una sola persona activa.')
                ->fillForm(fn (User $record): array => ['puesto_id' => $record->puesto_id, 'version' => $record->version]),
            AccionFilament::desde('nucleo.seguridad.registrar-cumpleanos-usuario', fn (User $record): array => ['usuario_id' => $record->id], $aviso)
                ->label('Cumpleaños')->icon('heroicon-o-cake')->color('gray')
                ->fillForm(fn (User $record): array => ['cumpleanos_dia' => $record->cumpleanos_dia, 'cumpleanos_mes' => $record->cumpleanos_mes, 'version' => $record->version]),
            AccionFilament::desde('nucleo.seguridad.desactivar-usuario', fn (User $record): array => ['usuario_id' => $record->id], $aviso)
                ->label('Desactivar')->icon('heroicon-o-no-symbol')->color('danger')
                ->modalDescription('La cuenta deja de iniciar sesión. Sus roles quedan, por si se vuelve a activar.')
                ->fillForm(fn (User $record): array => ['version' => $record->version])
                ->hidden(fn (User $record): bool => ! $record->es_activo),
            AccionFilament::desde('nucleo.seguridad.activar-usuario', fn (User $record): array => ['usuario_id' => $record->id], $aviso)
                ->label('Activar')->icon('heroicon-o-check-circle')->color('success')
                ->fillForm(fn (User $record): array => ['version' => $record->version])
                ->hidden(fn (User $record): bool => $record->es_activo),
        ];
    }

    public static function usuarioPuede(string $permiso): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->can($permiso);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarUsuarios::route('/'),
            'view' => VerUsuario::route('/{record}'),
        ];
    }
}
