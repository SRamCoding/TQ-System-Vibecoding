<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Resources\Usuarios;

use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Filament\SelectoresSeguridad;
use App\Modules\Nucleo\Models\User;
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
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Pestaña Roles de la ficha de un usuario (plan 1.4, D10): sus roles globales, con «Asignar rol» arriba y «Quitar»
 * en cada uno. Todo por las acciones `nucleo.seguridad.asignar-rol` y `quitar-rol`.
 */
final class RolesDeUsuario extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** Fija: el navegador no puede cambiarla para leer los roles de otra cuenta. */
    #[Locked]
    public int $usuarioId;

    /** El componente se puede montar aparte de la ficha: revisa el mismo permiso que ella. */
    public function mount(): void
    {
        abort_unless(Filament::auth()->user()?->can('usuarios.ver') ?? false, 403);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Role::query()->withCount('permissions')->whereIn('id', DB::table('model_has_roles')
                ->where('model_type', (new User)->getMorphClass())
                ->where('model_id', $this->usuarioId)
                ->select('role_id')))
            ->defaultSort('name')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Rol'),
                TextColumn::make('permissions_count')->label('Permisos'),
            ])
            ->headerActions([
                AccionFilament::desde('nucleo.seguridad.asignar-rol', fn (): array => ['usuario_id' => $this->usuarioId], $this->avisarGuardado(...),
                    controles: ['rol_id' => fn () => SelectoresSeguridad::rol('rol_id'), ...CamposDeControl::controles()])
                    ->label('Asignar rol')->icon('heroicon-o-plus')
                    ->modalDescription(CamposDeControl::AVISO),
            ])
            ->recordActions([
                AccionFilament::desde('nucleo.seguridad.quitar-rol', fn (Role $record): array => ['usuario_id' => $this->usuarioId, 'rol_id' => $record->id], $this->avisarGuardado(...))
                    ->label('Quitar')->icon('heroicon-o-x-mark')->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Los cruces de funciones que deje de juntar se cierran.'),
            ])
            ->emptyStateHeading('Sin roles globales')
            ->emptyStateDescription('Un rol global vale en toda la empresa. Para una sola unidad, usa «Roles por unidad».');
    }

    public function render(): View
    {
        return view('nucleo::filament.roles-de-usuario');
    }

    private function avisarGuardado(): void
    {
        Notification::make()->success()->title('Guardado.')->send();
        $this->dispatch('ficha-usuario-cambiada');
    }
}
