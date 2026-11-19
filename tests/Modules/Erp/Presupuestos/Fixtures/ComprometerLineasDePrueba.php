<?php

declare(strict_types=1);

namespace Tests\Modules\Erp\Presupuestos\Fixtures;

use App\Modules\Nucleo\Estados\Efecto;
use App\Modules\Nucleo\Imputacion\MomentoImputacion;
use App\Modules\Nucleo\Services\ExcesoDePartidaService;
use App\Modules\Nucleo\Services\ImputacionService;
use Illuminate\Database\Eloquent\Model;

/**
 * Al aprobar la orden de prueba: revisa el exceso con las partidas bloqueadas y **después** sube el comprometido de
 * cada línea, en la misma transacción (costura C12, paso b).
 */
final class ComprometerLineasDePrueba implements Efecto
{
    public function aplicar(Model $documento): array
    {
        $lineas = (new DefinicionOrdenDePrueba)->lineasDeCosto($documento);
        app(ExcesoDePartidaService::class)->alAprobar($lineas);
        $movidos = [];

        foreach ($lineas as $linea) {
            app(ImputacionService::class)->mover($linea->origen, MomentoImputacion::Comprometido, $linea->monto);
            $movidos[(string) $linea->origen->getKey()] = $linea->monto;
        }

        return ['lineas' => $movidos];
    }

    public function revertir(Model $documento, array $datos): void
    {
        foreach ($datos['lineas'] as $id => $monto) {
            app(ImputacionService::class)->mover(LineaOrdenDePrueba::query()->findOrFail((int) $id), MomentoImputacion::Comprometido, bcmul((string) $monto, '-1', 2));
        }
    }
}
