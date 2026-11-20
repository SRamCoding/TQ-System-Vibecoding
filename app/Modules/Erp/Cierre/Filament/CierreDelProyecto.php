<?php

declare(strict_types=1);

namespace App\Modules\Erp\Cierre\Filament;

use App\Modules\Erp\Cierre\Services\CierreService;
use App\Modules\Erp\Proyectos\Condiciones\VerificacionesCierre;
use App\Modules\Erp\Proyectos\Pestanas\ComponentePestanaProyecto;
use App\Modules\Nucleo\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Throwable;

/** MVP rápido (t9-cierre): la pestaña «Cierre» de la ficha del proyecto. */
final class CierreDelProyecto extends ComponentePestanaProyecto implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    private function puede(): bool
    {
        return auth()->user()?->can(CierreService::PERMISO_GESTIONAR) ?? false;
    }

    private function estado(): string
    {
        return (string) DB::table('proyectos as p')->join('estados as e', 'e.id', '=', 'p.estado_id')->where('p.id', $this->proyectoId)->value('e.codigo');
    }

    public function cerrarAction(): Action
    {
        return Action::make('cerrar')->label('Cerrar proyecto')->icon('heroicon-o-lock-closed')->color('success')
            ->disabled(fn (): bool => app(VerificacionesCierre::class)->bloqueos($this->proyectoId) !== [])
            ->visible(fn (): bool => $this->puede() && $this->estado() === 'en_ejecucion')
            ->modalDescription('Se congela el presupuesto contra real, se calcula el margen real y se habilita la devolución de la garantía. Deja de recibir costos.')
            ->schema([TextInput::make('comentario')->label('Comentario')->default('Acta de conformidad firmada.')->maxLength(300)])
            ->action(function (array $data): void {
                $u = auth()->user();

                try {
                    app(CierreService::class)->cerrar($this->proyectoId, $u instanceof User ? $u : abort(403), $data['comentario'] ?? null);
                    Notification::make()->success()->title('Proyecto cerrado.')->send();
                    $this->dispatch('proyecto-cambiado');
                } catch (Throwable $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
            });
    }

    public function leccionesAction(): Action
    {
        return Action::make('lecciones')->label('Escribir lo aprendido')->icon('heroicon-o-light-bulb')->color('gray')
            ->visible(fn (): bool => $this->puede() && DB::table('proyecto_cierres')->where('proyecto_id', $this->proyectoId)->exists())
            ->fillForm(fn (): array => ['lecciones' => DB::table('proyecto_cierres')->where('proyecto_id', $this->proyectoId)->value('lecciones')])
            ->schema([Textarea::make('lecciones')->label('Qué haríamos distinto en el próximo presupuesto')->rows(6)->required()])
            ->action(fn (array $data) => app(CierreService::class)->lecciones($this->proyectoId, $data['lecciones']));
    }

    public function devolverAction(): Action
    {
        return Action::make('devolver')->label('Registrar devolución')->size('sm')->color('gray')->requiresConfirmation()
            ->visible(fn (): bool => $this->puede())
            ->action(fn (array $arguments) => app(CierreService::class)->devolverGarantia((int) $arguments['garantia']));
    }

    public function render(): View
    {
        $cierre = DB::table('proyecto_cierres')->where('proyecto_id', $this->proyectoId)->first();

        return view('cierre::pestana', [
            'verificaciones' => app(VerificacionesCierre::class)->de($this->proyectoId),
            'bloqueos' => app(VerificacionesCierre::class)->bloqueos($this->proyectoId),
            'estado' => $this->estado(),
            'cierre' => $cierre,
            'partidas' => $cierre === null ? [] : json_decode($cierre->partidas, true),
            'garantias' => DB::table('contrato_garantias as g')->join('contratos as c', 'c.id', '=', 'g.contrato_id')->where('c.proyecto_id', $this->proyectoId)->orderBy('g.vencimiento')->get(['g.*']),
        ]);
    }
}
