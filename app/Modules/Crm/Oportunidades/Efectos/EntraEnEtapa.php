<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Efectos;

use App\Modules\Nucleo\Estados\Efecto;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Pone `en_etapa_desde` en cada cambio de etapa (plan 2.21, D2): es copia de la fecha de la fila del historial que
 * acaba de escribirse (la última entrada al estado actual), en la zona de la empresa; así no depende de dos relojes. Al
 * retroceder el contador vuelve a empezar. Revertir deja la fecha que había.
 */
final readonly class EntraEnEtapa implements Efecto
{
    public function aplicar(Model $documento): array
    {
        $antes = $documento->getAttribute('en_etapa_desde')?->toDateString();
        $entrada = HistorialEstado::query()
            ->where('documentable_type', $documento->getMorphClass())
            ->where('documentable_id', $documento->getKey())
            ->latest('id')
            ->value('fecha');
        $documento->setAttribute('en_etapa_desde', $entrada === null ? Calendario::hoy() : CarbonImmutable::parse($entrada)->timezone(Calendario::ZONA)->toDateString());
        $documento->save();

        return ['antes' => $antes];
    }

    public function revertir(Model $documento, array $datos): void
    {
        $documento->setAttribute('en_etapa_desde', $datos['antes']);
        $documento->save();
    }
}
