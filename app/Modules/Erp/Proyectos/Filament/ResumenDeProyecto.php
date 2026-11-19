<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Filament;

use App\Modules\Erp\Proyectos\ProyectosServiceProvider;
use App\Modules\Erp\Proyectos\Resumen\RegistroTarjetasResumen;
use App\Modules\Erp\Proyectos\Resumen\SeccionResumen;
use App\Modules\Erp\Proyectos\Services\PortadaProyectos;
use App\Modules\Nucleo\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * La pestaña «Resumen» de la ficha (plan 2.61, D5 y D6), la primera: las cuatro preguntas con las tarjetas que
 * registraron los módulos y la actividad reciente. Todo dato con desglose se toca y abre el panel deslizante con sus
 * filas. Revisa en cada petición que la persona vea proyectos; las tarjetas de costos ya las filtra el registro (D3).
 */
final class ResumenDeProyecto extends Component
{
    #[Locked]
    public int $proyectoId;

    public function boot(): void
    {
        abort_unless(auth()->user()?->can(ProyectosServiceProvider::PERMISO_VER) === true, 403);
    }

    public function render(): View
    {
        $usuario = auth()->user();
        abort_unless($usuario instanceof User, 403);

        return view('proyectos::ficha.resumen', [
            'secciones' => SeccionResumen::cases(),
            'tarjetas' => app(RegistroTarjetasResumen::class)->paraProyecto($this->proyectoId, $usuario),
            'actividad' => app(PortadaProyectos::class)->actividadDe($this->proyectoId),
        ]);
    }
}
