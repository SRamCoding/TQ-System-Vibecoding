<?php

declare(strict_types=1);

namespace App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\Pages;

use App\Modules\Crm\Licitaciones\Enums\EstadoLicitacion;
use App\Modules\Crm\Licitaciones\Filament\AccionesLicitacion;
use App\Modules\Crm\Licitaciones\Filament\Resources\Licitaciones\LicitacionResource;
use App\Modules\Crm\Licitaciones\Models\Licitacion;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

/**
 * MVP rápido (t1-licitaciones): la ficha. Arriba, las acciones de su estado; abajo, «Datos generales» y las pestañas
 * Cronograma, Expediente, Consultas y Resultado.
 */
final class VerLicitacion extends ViewRecord
{
    protected static string $resource = LicitacionResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Datos generales';
    }

    public function getTitle(): string
    {
        return "{$this->registro()->codigo} · ".$this->registro()->cuenta?->party?->razon_social;
    }

    public function getSubheading(): string
    {
        $l = $this->registro();
        $proximo = $l->hitos()->where('es_cumplido', false)->first();
        $faltan = $l->documentos()->where(fn ($q) => $q->where('es_listo', false)->orWhere('es_confirmado', false))->count();
        $partes = [$l->estado->etiqueta()];

        if ($l->estado->esAbierta() && $proximo !== null) {
            $dias = (int) CarbonImmutable::parse(Calendario::hoy())->diffInDays($proximo->fecha, false);
            $partes[] = "{$proximo->etapa} ".($dias >= 0 ? "en {$dias} días" : 'vencida hace '.abs($dias).' días');
        }

        if ($l->estado === EstadoLicitacion::EnPreparacion) {
            $partes[] = $faltan === 0 ? 'expediente completo' : "faltan {$faltan} documentos del expediente";
        }

        if ($l->responsable !== null) {
            $partes[] = "responsable: {$l->responsable->name}";
        }

        return implode(' · ', $partes);
    }

    private function registro(): Licitacion
    {
        /** @var Licitacion $l */
        $l = $this->record;

        return $l;
    }

    protected function getHeaderActions(): array
    {
        return [
            ...array_values(AccionesLicitacion::todas(fn (): Licitacion => $this->registro(), fn (Closure $paso, string $exito) => $this->hacer($paso, $exito))),
            Action::make('irOportunidad')->label('Ir a la oportunidad')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->visible(fn (): bool => $this->registro()->oportunidad_id !== null)
                ->url(fn (): string => VerOportunidad::getUrl(['record' => $this->registro()->oportunidad_id])),
        ];
    }

    private function hacer(Closure $paso, string $exito): void
    {
        try {
            $paso();
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($exito)->send();
        $this->record->refresh();
        $this->redirect(self::getUrl(['record' => $this->record]));
    }
}
