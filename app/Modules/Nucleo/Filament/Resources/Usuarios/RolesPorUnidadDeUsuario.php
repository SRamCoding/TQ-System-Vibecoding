<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Usuarios;

use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\SelectoresSeguridad;
use App\Modules\Nucleo\Models\PermisoPorAmbito;
use App\Modules\Nucleo\Tiempo\Calendario;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
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
use Livewire\Component;

/**
 * Pestaña Roles por unidad de la ficha de un usuario (plan 1.4, D7 y D10): roles que valen solo dentro de una
 * unidad organizativa, con sus fechas. «Hasta» es el último día vigente, en hora de Lima.
 */
final class RolesPorUnidadDeUsuario extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $usuarioId;

    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can('usuarios.ver') ?? false, 403);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PermisoPorAmbito::query()->with(['rol', 'ambito'])
                ->where('user_id', $this->usuarioId)
                ->where('ambito_type', 'unidad_organizativa'))
            ->defaultSort('id', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('rol.name')->label('Rol'),
                TextColumn::make('ambito.nombre')->label('Unidad'),
                TextColumn::make('desde')->label('Desde')->date('d/m/Y')->placeholder('Sin inicio'),
                TextColumn::make('hasta')->label('Hasta')->date('d/m/Y')->placeholder('Sin fin'),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->state(fn (PermisoPorAmbito $record): string => self::estado($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Vigente' => 'success',
                        'Programado' => 'info',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                AccionFilament::desde(
                    'nucleo.seguridad.asignar-rol-en-ambito',
                    fn (): array => ['usuario_id' => $this->usuarioId, 'ambito_type' => 'unidad_organizativa'],
                    $this->avisarGuardado(...),
                    controles: [
                        'rol_id' => fn () => SelectoresSeguridad::rol('rol_id'),
                        'ambito_id' => fn () => SelectoresSeguridad::unidad('ambito_id'),
                        ...CamposDeControl::controles(),
                    ],
                )
                    ->label('Asignar rol en una unidad')->icon('heroicon-o-plus')
                    ->modalDescription('Vale solo dentro de esa unidad. «Hasta» es el último día. '.CamposDeControl::AVISO),
            ])
            ->recordActions([
                AccionFilament::desde('nucleo.seguridad.quitar-rol-en-ambito', fn (PermisoPorAmbito $record): array => ['asignacion_id' => $record->id], $this->avisarGuardado(...))
                    ->label('Quitar')->icon('heroicon-o-x-mark')->color('danger')
                    ->modalDescription('Deja de valer hoy. Si todavía no empezaba, se borra.')
                    ->fillForm(fn (PermisoPorAmbito $record): array => ['version' => $record->version])
                    ->hidden(fn (PermisoPorAmbito $record): bool => self::estado($record) === 'Terminado'),
            ])
            ->emptyStateHeading('Sin roles por unidad');
    }

    public function render(): View
    {
        return view('nucleo::filament.roles-por-unidad-de-usuario');
    }

    private static function estado(PermisoPorAmbito $asignacion): string
    {
        $hoy = Calendario::hoy();

        return match (true) {
            $asignacion->hasta !== null && $asignacion->hasta->toDateString() < $hoy => 'Terminado',
            $asignacion->desde !== null && $asignacion->desde->toDateString() > $hoy => 'Programado',
            default => 'Vigente',
        };
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        $this->dispatch('ficha-usuario-cambiada');
    }
}
