<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Efectos;

use App\Modules\Erp\Presupuestos\Estados\DefinicionPresupuesto;
use App\Modules\Erp\Presupuestos\Models\Presupuesto;
use App\Modules\Nucleo\Estados\Efecto;
use App\Modules\Nucleo\Services\TransicionesService;
use Illuminate\Database\Eloquent\Model;

/**
 * Al ofertarse o traerse al proyecto una versión, las versiones anteriores del mismo número que seguían ofertadas pasan
 * a reemplazadas (plan 2.22, D8: en preventa, otros costos van en una versión nueva del presupuesto ofertado). Lo
 * ofertado antes queda congelado como lo que se ofertó.
 */
final readonly class ReemplazarOfertadasAnteriores implements Efecto
{
    public function aplicar(Model $documento): array
    {
        if (! $documento instanceof Presupuesto) {
            return [];
        }

        $anteriores = Presupuesto::query()
            ->where('numero', $documento->numero)
            ->where('numero_version', '<', $documento->numero_version)
            ->whereHas('estado', fn ($q) => $q->where('codigo', DefinicionPresupuesto::OFERTADO))
            ->lockForUpdate()
            ->get();

        foreach ($anteriores as $anterior) {
            app(TransicionesService::class)->transicionarComoSistema($anterior, DefinicionPresupuesto::REEMPLAZAR);
        }

        return ['reemplazados' => $anteriores->modelKeys()];
    }

    public function revertir(Model $documento, array $datos): void
    {
        // Reemplazado es un estado final, y ni lo ofertado ni lo aprobado se anulan: no hay nada que deshacer.
    }
}
