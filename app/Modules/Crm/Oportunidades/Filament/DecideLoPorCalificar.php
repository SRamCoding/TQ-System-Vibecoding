<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Filament;

use App\Modules\Crm\Campanias\Filament\Resources\Leads\LeadResource;
use App\Modules\Crm\Campanias\Models\Lead;
use App\Modules\Crm\Licitaciones\Filament\AccionesLicitacion;
use App\Modules\Crm\Licitaciones\Models\Licitacion;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use RuntimeException;

/**
 * Observaciones 7.1 y 8.2: lo que está «Por calificar» se resuelve donde se vea (Mis oportunidades y el panel del
 * Embudo): Calificar / Descartar un lead y Participar / No participar una licitación, con los formularios de siempre.
 * Quien lo usa tiene `calcularCifras()` (MuestraLasVistas).
 */
trait DecideLoPorCalificar
{
    /** La tarjeta por calificar sobre la que se abrió una acción (lead o licitación). */
    public ?int $porCalificarId = null;

    /** Abre una acción sobre una tarjeta por calificar (lead o licitación). */
    public function decidir(string $accion, int $id): void
    {
        if (! in_array($accion, ['calificarLead', 'descartarLead', 'participar', 'noParticipar'], true)) {
            return;
        }

        $this->porCalificarId = $id;
        $this->mountAction($accion);
    }

    public function calificarLeadAction(): Action
    {
        return LeadResource::accionConvertir()->name('calificarLead')->label('Calificar')
            ->record(fn (): ?Lead => Lead::query()->find($this->porCalificarId))
            ->modalHeading(fn (): string => 'Calificar · '.Lead::query()->whereKey($this->porCalificarId)->value('nombre_contacto'))
            ->after(fn () => $this->calcularCifras());
    }

    public function descartarLeadAction(): Action
    {
        return LeadResource::accionDescartar()->name('descartarLead')
            ->record(fn (): ?Lead => Lead::query()->find($this->porCalificarId))
            ->after(fn () => Notification::make()->success()->title('Lead descartado.')->send());
    }

    public function participarAction(): Action
    {
        return $this->accionLicitacion('participar');
    }

    public function noParticiparAction(): Action
    {
        return $this->accionLicitacion('noParticipar');
    }

    private function accionLicitacion(string $nombre): Action
    {
        $acciones = AccionesLicitacion::todas(
            fn (): Licitacion => Licitacion::query()->findOrFail($this->porCalificarId),
            function (Closure $hacer, string $mensaje): void {
                try {
                    $hacer();
                } catch (RuntimeException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title($mensaje)->send();
                $this->calcularCifras();
            },
        );

        return $acciones[$nombre];
    }
}
