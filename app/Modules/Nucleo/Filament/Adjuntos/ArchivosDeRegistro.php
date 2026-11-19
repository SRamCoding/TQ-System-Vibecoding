<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Adjuntos;

use App\Modules\Nucleo\Adjuntos\ConAdjuntos;
use App\Modules\Nucleo\Estados\DocumentoConEstados;
use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Models\Adjunto;
use App\Modules\Nucleo\Models\TipoAdjunto;
use App\Modules\Nucleo\Models\User;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Number;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pestaña «Archivos» de cualquier registro que lleve adjuntos (plan 1.8, D9; se estrena en la ficha de una party). Los
 * vigentes que la persona puede ver, con su descarga protegida; agregar, reemplazar y quitar por las acciones
 * registradas `nucleo.adjuntos.*`. En un documento no editable solo se ofrece agregar (plan 1.5, D8).
 */
final class ArchivosDeRegistro extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** Fijos: el navegador no los puede cambiar para leer los archivos de otro registro. */
    #[Locked]
    public string $adjuntableType;

    #[Locked]
    public int $adjuntableId;

    public function mount(): void
    {
        abort_unless($this->registro()->puedeVerAdjuntos($this->usuario()), 403);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Adjunto::query()
                ->with(['tipo', 'autor:id,name'])
                ->where('adjuntable_type', $this->adjuntableType)
                ->where('adjuntable_id', $this->adjuntableId)
                ->whereNull('quitado_at')
                // Los sensibles, solo los de los grupos que la persona puede ver (D5).
                ->where(fn (Builder $query): Builder => $query->whereNull('grupo_sensible')->orWhereIn('grupo_sensible', $this->gruposVisibles()))
                ->orderByDesc('id'))
            ->paginated(false)
            ->columns([
                TextColumn::make('nombre_original')->label('Archivo')->wrap()
                    ->url(fn (Adjunto $record): string => route('filament.app.adjuntos.descargar', ['adjunto' => $record->id]))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->description(fn (Adjunto $record): ?string => $record->tipo?->nombre),
                TextColumn::make('tamano_bytes')->label('Tamaño')
                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state, 1)),
                TextColumn::make('autor.name')->label('Subido por'),
                TextColumn::make('created_at')->label('Cuándo')->dateTime('d/m/Y H:i', 'America/Lima'),
            ])
            ->headerActions([
                AccionFilament::desde(
                    'nucleo.adjuntos.agregar',
                    fn (): array => ['adjuntable_type' => $this->adjuntableType, 'adjuntable_id' => $this->adjuntableId],
                    $this->avisar(...),
                    controles: ['tipo' => fn () => Select::make('tipo')->options(fn (): array => $this->tipos())],
                )
                    ->label('Adjuntar archivo')->icon('heroicon-o-paper-clip')
                    ->hidden(fn (): bool => ! $this->registro()->puedeAdjuntar($this->usuario())),
            ])
            ->recordActions([
                AccionFilament::desde('nucleo.adjuntos.reemplazar', fn (Adjunto $record): array => ['adjunto_id' => $record->id], $this->avisar(...))
                    ->label('Reemplazar')->icon('heroicon-o-arrow-path')
                    ->hidden(fn (): bool => ! $this->sePuedeCambiar()),
                AccionFilament::desde('nucleo.adjuntos.quitar', fn (Adjunto $record): array => ['adjunto_id' => $record->id], $this->avisar(...))
                    ->label('Quitar')->icon('heroicon-o-x-mark')->color('danger')->requiresConfirmation()
                    ->modalDescription('Deja de verse aquí, pero queda anotado quién lo quitó y cuándo, y el archivo se conserva.')
                    ->hidden(fn (): bool => ! $this->sePuedeCambiar()),
            ])
            ->emptyStateHeading('Sin archivos')
            ->emptyStateDescription('Adjunta aquí fichas, contratos, certificados…');
    }

    public function render(): View
    {
        return view('nucleo::filament.archivos-de-registro');
    }

    /** Y avisa a la pantalla que la contiene, por si muestra algo de sus archivos (el logo en el asistente; plan 1.15). */
    private function avisar(): void
    {
        Notification::make()->success()->title('Listo.')->send();
        $this->dispatch('archivos-cambiados');
    }

    /** Puede cambiar los archivos y el registro se edita (un documento no editable solo admite agregar). */
    private function sePuedeCambiar(): bool
    {
        $registro = $this->registro();

        return $registro->puedeAdjuntar($this->usuario()) && ! ($registro instanceof DocumentoConEstados && ! $registro->esEditable());
    }

    /**
     * Los tipos de adjunto activos que sirven para este registro.
     *
     * @return array<string, string>
     */
    private function tipos(): array
    {
        return TipoAdjunto::query()
            ->where('es_activo', true)
            ->where(fn (Builder $query): Builder => $query->whereNull('documento_type')->orWhere('documento_type', $this->adjuntableType))
            ->orderBy('nombre')
            ->pluck('nombre', 'codigo')
            ->all();
    }

    /**
     * @return list<string>
     */
    private function gruposVisibles(): array
    {
        $usuario = $this->usuario();

        return array_values(array_map(
            fn (GrupoSensible $grupo): string => $grupo->value,
            array_filter(GrupoSensible::cases(), fn (GrupoSensible $grupo): bool => $usuario->can($grupo->permiso())),
        ));
    }

    private function registro(): Model&ConAdjuntos
    {
        $clase = Relation::getMorphedModel($this->adjuntableType);
        $registro = $clase === null ? null : $clase::query()->find($this->adjuntableId);
        abort_unless($registro instanceof ConAdjuntos, 404);

        return $registro;
    }

    private function usuario(): User
    {
        $usuario = Filament::auth()->user();
        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }
}
