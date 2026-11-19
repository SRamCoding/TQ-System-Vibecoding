<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Pages;

use App\Modules\Nucleo\Excepciones\ErrorDeNegocio;
use App\Modules\Nucleo\Models\Notificacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * «Mis avisos» (plan 1.4y, D3): todos los avisos de la persona, con un filtro que abre en los leídos (los no leídos
 * también están en la campana). En cada uno, «Abrir» si lleva enlace, y «Marcar como leído» o «Marcar como no leído»
 * (vuelve a la campana), por las acciones registradas. Nadie borra un aviso. Los leídos hace más de `avisos.dias_leidos`
 * días ya pasaron al archivo histórico (D4 y D6). Se abre desde el pie de la campana y desde el menú de la foto.
 */
final class MisAvisos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'mis-avisos';

    protected static ?string $title = 'Mis avisos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    /** No va en la barra de módulos: se abre desde la campana y el menú de la foto. */
    protected static bool $shouldRegisterNavigation = false;

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->suyos())
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('titulo')->label('Aviso')->state(fn (Notificacion $record): string => (string) ($record->data['title'] ?? ''))
                    ->description(fn (Notificacion $record): ?string => isset($record->data['body']) ? (string) $record->data['body'] : null)->wrap(),
                TextColumn::make('created_at')->label('Llegó')->dateTime('d/m/Y H:i', 'America/Lima')->sortable(),
                TextColumn::make('read_at')->label('Leído')->dateTime('d/m/Y H:i', 'America/Lima')->placeholder('Sin leer')->sortable(),
            ])
            ->filters([
                SelectFilter::make('estado')->label('Ver')->options(['leidos' => 'Leídos', 'no_leidos' => 'No leídos'])->placeholder('Todos')
                    ->default('leidos')
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'leidos' => $query->whereNotNull('read_at'),
                        'no_leidos' => $query->whereNull('read_at'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                Action::make('abrir')->label('Abrir')->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Notificacion $record): ?string => self::enlace($record))
                    ->hidden(fn (Notificacion $record): bool => self::enlace($record) === null),
                Action::make('marcarLeido')->label('Marcar como leído')->icon('heroicon-m-check')
                    ->hidden(fn (Notificacion $record): bool => $record->read_at !== null)
                    ->action(fn (Notificacion $record) => $this->ejecutar('marcar-aviso-leido', $record->id)),
                Action::make('marcarNoLeido')->label('Marcar como no leído')->icon('heroicon-m-arrow-uturn-left')
                    ->hidden(fn (Notificacion $record): bool => $record->read_at === null)
                    ->action(fn (Notificacion $record) => $this->ejecutar('marcar-aviso-no-leido', $record->id)),
            ])
            ->emptyStateHeading('No hay avisos aquí')
            ->emptyStateDescription('Cambia el filtro para ver los demás.');
    }

    /** @return Builder<Notificacion> */
    private function suyos(): Builder
    {
        $usuario = Filament::auth()->user();

        // Los mismos que muestra la campana (Filament solo dibuja los de su formato).
        return Notificacion::query()
            ->where('notifiable_type', $usuario?->getMorphClass())
            ->where('notifiable_id', $usuario?->getKey() ?? 0)
            ->where('data->format', 'filament');
    }

    /** El enlace de «Abrir» que guardó el aviso, si tiene. */
    private static function enlace(Notificacion $aviso): ?string
    {
        foreach ((array) ($aviso->data['actions'] ?? []) as $accion) {
            if (is_array($accion) && ($accion['name'] ?? null) === 'abrir' && is_string($accion['url'] ?? null)) {
                return $accion['url'];
            }
        }

        return null;
    }

    private function ejecutar(string $accion, int $id): void
    {
        $usuario = Filament::auth()->user();

        if (! $usuario instanceof User) {
            return;
        }

        try {
            app(EjecutorAcciones::class)->ejecutar("nucleo.{$accion}", ['aviso_id' => $id], $usuario);
        } catch (ErrorDeNegocio|ValidationException $e) {
            Notification::make()->title($e instanceof ErrorDeNegocio ? $e->getMessage() : 'No se pudo marcar el aviso.')->danger()->send();
        }
    }
}
