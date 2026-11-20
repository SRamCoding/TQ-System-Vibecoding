<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Filament;

use App\Modules\Crm\Licitaciones\Enums\EstadoLicitacion;
use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\Pages\VerLicitacion;
use App\Modules\Crm\Licitaciones\Models\Licitacion;
use App\Modules\Crm\Licitaciones\Services\LicitacionesService;
use App\Modules\Crm\Oportunidades\Pestanas\ComponentePestanaOportunidad;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use RuntimeException;

/**
 * MV-023: la licitación como sección de la ficha de su oportunidad: bases, cronograma, expediente, consultas, propuesta y
 * resultado, con las mismas acciones que su ficha. El resultado cierra la oportunidad (LicitacionesService).
 */
final class LicitacionDeOportunidad extends ComponentePestanaOportunidad implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public static function tieneLicitacion(int $oportunidadId): bool
    {
        return Licitacion::query()->where('oportunidad_id', $oportunidadId)->exists();
    }

    public function licitacion(): Licitacion
    {
        return Licitacion::query()->with(['hitos', 'documentos', 'consultas', 'participantes', 'responsable'])->where('oportunidad_id', $this->oportunidadId)->firstOrFail();
    }

    /** @return array<string, Action> */
    private function acciones(): array
    {
        return AccionesLicitacion::todas(fn (): Licitacion => $this->licitacion(), function (Closure $paso, string $exito): void {
            try {
                $paso();
            } catch (RuntimeException $e) {
                Notification::make()->danger()->title($e->getMessage())->send();

                return;
            }

            Notification::make()->success()->title($exito)->send();
            // La oportunidad pudo cerrarse con el resultado: se vuelve a dibujar entera.
            $this->redirect(request()->header('Referer') ?? '/oportunidades/'.$this->oportunidadId);
        });
    }

    public function participarAction(): Action
    {
        return $this->acciones()['participar'];
    }

    public function noParticiparAction(): Action
    {
        return $this->acciones()['noParticipar'];
    }

    public function confirmarSugerenciasAction(): Action
    {
        return $this->acciones()['confirmarSugerencias'];
    }

    public function presentarAction(): Action
    {
        return $this->acciones()['presentar'];
    }

    public function resultadoAction(): Action
    {
        return $this->acciones()['resultado'];
    }

    public function render(): View
    {
        $l = $this->licitacion();
        $hoy = CarbonImmutable::parse(Calendario::hoy());

        return view('licitaciones::oportunidad.licitacion', [
            'l' => $l,
            'hoy' => $hoy,
            'historico' => app(LicitacionesService::class)->historico($l->cuenta_id, $l->id),
            'urlFicha' => VerLicitacion::getUrl(['record' => $l->id]),
            'cerrada' => in_array($l->estado, [EstadoLicitacion::BuenaPro, EstadoLicitacion::Perdida, EstadoLicitacion::Desierta, EstadoLicitacion::NoParticipa], true),
        ]);
    }
}
