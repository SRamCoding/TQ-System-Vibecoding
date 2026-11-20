<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Filament\Plantillas;

use App\Modules\Nucleo\Filament\AccionFilament;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Plantillas\RegistroPlantillas;
use App\Modules\Nucleo\Plantillas\ResumenPlantilla;
use App\Modules\Nucleo\Services\PlantillasService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Las plantillas de un tipo en el panel deslizante (ADR 0022; plan 2.11, paso 5), la pieza común que cada módulo pone
 * en su pantalla con `nucleo::filament.plantillas.panel`: las mías y las de la empresa, con su versión vigente; «Usar»
 * avisa a la pantalla (`plantilla-elegida`), que arma la propuesta con su contexto. Con un documento abierto, ofrece
 * «Guardar como plantilla» y «Actualizar desde este documento». Todo por las acciones `nucleo.plantillas.*`; el
 * contenido nunca llega aquí (D5: sin el permiso del grupo sensible, ni siquiera la lista).
 */
final class PlantillasDeTipo extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public string $tipo;

    /** El documento abierto en la pantalla, del que se guarda o actualiza; null en una pantalla sin documento. */
    #[Locked]
    public ?int $documentoId = null;

    /** Falso donde no hay a qué aplicar (por ejemplo, un documento que ya existe). */
    #[Locked]
    public bool $ofrecerUsar = true;

    public function mount(): void
    {
        abort_unless(app(RegistroPlantillas::class)->existe($this->tipo) && $this->usuario() !== null, 403);
    }

    public function table(Table $table): Table
    {
        $conDocumento = $this->documentoId !== null;
        $propia = fn (array $record): bool => (bool) $record['es_propia'];
        $cambia = fn (array $record): bool => (bool) $record['puede_cambiar'];

        return $table
            ->records(fn (?array $filters): array => $this->filas($filters['alcance']['value'] ?? null))
            ->paginated(false)
            ->heading(app(RegistroPlantillas::class)->obtener($this->tipo)->nombre())
            ->description('Las tuyas y las de la empresa. Al usar una, se copia: lo que cambies después no la toca.')
            ->columns([
                TextColumn::make('nombre')->label('Plantilla')->wrap()->weight('bold')
                    ->description(fn (array $record): ?string => $record['descripcion']),
                TextColumn::make('alcance')->label('De')->badge()
                    ->color(fn (array $record): string => $record['es_de_la_empresa'] ? 'primary' : 'gray'),
                TextColumn::make('vigente')->label('Versión'),
            ])
            ->filters([
                SelectFilter::make('alcance')->label('Ver')->options(['mias' => 'Mías', 'empresa' => 'De la empresa']),
            ])
            ->headerActions([
                AccionFilament::desde('nucleo.plantillas.guardar', fn (): array => ['tipo' => $this->tipo, 'documento_id' => (int) $this->documentoId], $this->avisar('Guardada como plantilla tuya.'))
                    ->name('guardarPlantilla')->label('Guardar como plantilla')->icon('heroicon-o-bookmark')
                    ->modalDescription('Queda como plantilla tuya, sin montos de este caso ni personas. Para que la usen todos, publícala después.')
                    ->hidden(! $conDocumento),
            ])
            ->recordActions([
                Action::make('usarPlantilla')->label('Usar')->icon('heroicon-o-arrow-down-on-square-stack')->button()
                    ->action(fn (array $record) => $this->dispatch('plantilla-elegida', plantillaId: (int) $record['id'], tipo: $this->tipo))
                    ->hidden(! $this->ofrecerUsar),
                ActionGroup::make([
                    AccionFilament::desde('nucleo.plantillas.actualizar', fn (array $record): array => ['plantilla_id' => (int) $record['id'], 'documento_id' => (int) $this->documentoId], $this->avisar('Plantilla actualizada: desde ahora se usa la versión nueva.'))
                        ->name('actualizarPlantilla')->label('Actualizar desde este documento')->icon('heroicon-o-arrow-path')
                        ->fillForm(fn (array $record): array => ['version' => (int) $record['version']])
                        ->hidden(fn (array $record): bool => ! $conDocumento || ! $cambia($record)),
                    AccionFilament::desde('nucleo.plantillas.editar', fn (array $record): array => ['plantilla_id' => (int) $record['id']], $this->avisar('Guardado.'))
                        ->name('editarPlantilla')->label('Cambiar nombre')->icon('heroicon-o-pencil-square')
                        ->fillForm(fn (array $record): array => ['nombre' => $record['nombre'], 'descripcion' => $record['descripcion'], 'version' => (int) $record['version']])
                        ->hidden(fn (array $record): bool => ! $cambia($record)),
                    AccionFilament::desde('nucleo.plantillas.publicar', fn (array $record): array => ['plantilla_id' => (int) $record['id']], $this->avisar('Publicada: ya la puede usar todo el equipo.'))
                        ->name('publicarPlantilla')->label('Publicar en la empresa')->icon('heroicon-o-megaphone')
                        ->modalDescription('Se crea una copia de la empresa con su versión de hoy; la tuya sigue siendo tuya.')
                        ->fillForm(fn (array $record): array => ['nombre' => $record['nombre']])
                        ->hidden(fn (array $record): bool => ! $propia($record) || ! $this->puedePublicar()),
                    AccionFilament::desde('nucleo.plantillas.desactivar', fn (array $record): array => ['plantilla_id' => (int) $record['id']], $this->avisar('Plantilla desactivada.'))
                        ->name('desactivarPlantilla')->label('Desactivar')->icon('heroicon-o-eye-slash')->color('danger')
                        ->modalDescription('Deja de ofrecerse. Lo que ya se creó con ella sigue diciendo de dónde salió.')
                        ->fillForm(fn (array $record): array => ['version' => (int) $record['version']])
                        ->hidden(fn (array $record): bool => ! $cambia($record)),
                ]),
            ])
            ->emptyStateHeading('Todavía no hay plantillas de este tipo')
            ->emptyStateDescription($conDocumento
                ? 'Cuando este documento esté bien armado, «Guardar como plantilla» lo deja listo para la próxima vez.'
                : 'Se crean desde un documento bien armado, con «Guardar como plantilla».');
    }

    public function render(): View
    {
        return view('nucleo::filament.plantillas.tabla');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function filas(?string $alcance): array
    {
        $usuario = $this->usuario();
        $filas = [];

        foreach ($usuario === null ? [] : app(PlantillasService::class)->disponibles($this->tipo, $usuario) as $p) {
            if (($alcance === 'mias' && $p->esDeLaEmpresa) || ($alcance === 'empresa' && ! $p->esDeLaEmpresa)) {
                continue;
            }

            $filas["p{$p->id}"] = self::fila($p);
        }

        return $filas;
    }

    /**
     * @return array<string, mixed>
     */
    private static function fila(ResumenPlantilla $p): array
    {
        return [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'descripcion' => $p->descripcion,
            'alcance' => $p->esDeLaEmpresa ? 'De la empresa' : 'Mía',
            'es_de_la_empresa' => $p->esDeLaEmpresa,
            'es_propia' => $p->esPropia,
            'puede_cambiar' => $p->puedeCambiar,
            'vigente' => "v{$p->numeroVigente} · {$p->vigenteDesde->setTimezone('America/Lima')->format('d/m/Y')}",
            'version' => $p->version,
        ];
    }

    private function puedePublicar(): bool
    {
        return $this->usuario()?->can(app(RegistroPlantillas::class)->obtener($this->tipo)->permisoPublicar()) ?? false;
    }

    private function usuario(): ?User
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User ? $usuario : null;
    }

    private function avisar(string $titulo): \Closure
    {
        return function () use ($titulo): void {
            Notification::make()->success()->title($titulo)->send();
            $this->resetTable();
        };
    }
}
