<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\SolicitudesPermisoService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MVP rápido (ficha op-solicitudes-permiso): las solicitudes de permiso. Quien administra Roles y permisos ve todas y
 * las concede por rol o las rechaza; los demás ven las suyas.
 */
final class SolicitudesPermiso extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'solicitudes-permiso';

    protected static ?string $title = 'Solicitudes de permiso';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return Filament::auth()->user() instanceof User;
    }

    private static function administra(): bool
    {
        return auth()->user()?->can(RolesPermisos::PERMISO) ?? false;
    }

    public function getSubheading(): string
    {
        $n = DB::table('solicitudes_permiso')->where('estado', 'pendiente')->count();

        return self::administra() ? "{$n} pendientes" : '';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $s = app(SolicitudesPermisoService::class);

        return $table
            ->records(fn (?array $filters): array => DB::table('solicitudes_permiso as s')->join('users as u', 'u.id', '=', 's.user_id')
                ->when(! self::administra(), fn ($q) => $q->where('s.user_id', auth()->id()))
                ->when(($filters['estado']['value'] ?? 'pendiente') !== 'todas', fn ($q) => $q->where('s.estado', $filters['estado']['value'] ?? 'pendiente'))
                ->orderByDesc('s.created_at')->get(['s.*', 'u.name'])->map(fn (object $r): array => ['__key' => (string) $r->id, ...(array) $r, 'permiso_nombre' => $s->nombre($r->permiso),
                    'rol' => $r->rol_id === null ? null : DB::table('roles')->where('id', $r->rol_id)->value('name')])->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Quién')->weight('bold')->description(fn (array $record): string => CarbonImmutable::parse($record['created_at'])->format('d/m/Y H:i')),
                TextColumn::make('permiso_nombre')->label('Permiso')->description(fn (array $record): string => "para «{$record['accion']}» · {$record['permiso']}")->wrap(),
                TextColumn::make('motivo')->label('Para qué')->wrap()->placeholder('—'),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state, array $record): string => ['pendiente' => 'Pendiente', 'concedida' => 'Concedida al rol «'.$record['rol'].'»', 'rechazada' => 'Rechazada'][$state])
                    ->color(fn (string $state): string => ['pendiente' => 'warning', 'concedida' => 'success', 'rechazada' => 'danger'][$state])->description(fn (array $record): ?string => $record['comentario']),
            ])
            ->filters([SelectFilter::make('estado')->label('Ver')->default('pendiente')->options(['pendiente' => 'Pendientes', 'concedida' => 'Concedidas', 'rechazada' => 'Rechazadas', 'todas' => 'Todas'])->query(fn ($q) => $q)])
            ->recordActions([
                Action::make('conceder')->label('Conceder')->button()->color('success')->hidden(fn (array $record): bool => $record['estado'] !== 'pendiente' || ! self::administra())
                    ->modalDescription(fn (array $record): string => "Se le da «{$record['permiso_nombre']}» al rol elegido: lo reciben todas las personas con ese rol.")
                    ->fillForm(fn (array $record): array => ['rol' => DB::table('model_has_roles')->where('model_id', $record['user_id'])->value('role_id')])
                    ->schema([Select::make('rol')->label('Rol')->required()->options(fn (): array => DB::table('roles')->orderBy('name')->pluck('name', 'id')->all())
                        ->helperText('Por omisión, el primer rol de la persona.')])
                    ->action(fn (array $record, array $data) => $this->hacer(fn () => app(SolicitudesPermisoService::class)->conceder((int) $record['id'], (int) $data['rol'], auth()->user()), 'Concedido.')),
                Action::make('rechazar')->label('Rechazar')->color('gray')->hidden(fn (array $record): bool => $record['estado'] !== 'pendiente' || ! self::administra())
                    ->schema([Textarea::make('comentario')->label('Comentario')->required()->rows(2)])
                    ->action(fn (array $record, array $data) => $this->hacer(fn () => app(SolicitudesPermisoService::class)->rechazar((int) $record['id'], $data['comentario'], auth()->user()), 'Rechazada.')),
            ])
            ->emptyStateHeading('Sin solicitudes');
    }

    private function hacer(\Closure $paso, string $exito): void
    {
        try {
            $paso();
            Notification::make()->success()->title($exito)->send();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }
}
