<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/** MVP rápido (ficha op-asistente): los tickets que propuso el asistente y la persona confirmó (soporte los atiende). */
final class TicketsAyuda extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'tickets-ayuda';

    protected static ?string $title = 'Tickets de ayuda';

    protected static string|BackedEnum|null $navigationIcon = 'tq-ticket-soporte';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return Filament::auth()->user() instanceof User;
    }

    private static function soporte(): bool
    {
        return auth()->user()?->can(RolesPermisos::PERMISO) ?? false;
    }

    public function getSubheading(): string
    {
        return 'Los abre el asistente «n_n» cuando alguien le cuenta un problema y lo confirma. Llevan la pantalla y la conversación.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => DB::table('tickets_ayuda as t')->join('users as u', 'u.id', '=', 't.user_id')->when(! self::soporte(), fn ($q) => $q->where('t.user_id', auth()->id()))
                ->orderByDesc('t.created_at')->get(['t.*', 'u.name'])->map(fn (object $r): array => ['__key' => (string) $r->id, ...(array) $r])->all())
            ->paginated(false)
            ->columns([
                TextColumn::make('codigo')->label('Ticket')->fontFamily('mono')->description(fn (array $record): string => CarbonImmutable::parse($record['created_at'])->format('d/m/Y H:i')),
                TextColumn::make('name')->label('Quién'),
                TextColumn::make('resumen')->label('Qué pasó')->wrap()->description(fn (array $record): string => 'en '.$record['pantalla']),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (string $state): string => ['abierto' => 'Abierto', 'en_curso' => 'En curso', 'resuelto' => 'Resuelto'][$state])
                    ->color(fn (string $state): string => ['abierto' => 'warning', 'en_curso' => 'info', 'resuelto' => 'success'][$state]),
            ])
            ->recordActions([
                Action::make('avanzar')->label(fn (array $record): string => $record['estado'] === 'abierto' ? 'Tomar' : 'Resolver')->button()->color('gray')
                    ->hidden(fn (array $record): bool => $record['estado'] === 'resuelto' || ! self::soporte())
                    ->action(fn (array $record) => DB::table('tickets_ayuda')->where('id', $record['id'])->update(['estado' => $record['estado'] === 'abierto' ? 'en_curso' : 'resuelto', 'updated_at' => now()])),
                Action::make('ver')->label('Conversación')->color('gray')->modalSubmitAction(false)->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn (array $record) => new HtmlString('<pre style="white-space:pre-wrap;font-size:13px">'.e($record['conversacion'] ?? '—').'</pre>')),
            ])
            ->emptyStateHeading('Sin tickets');
    }
}
