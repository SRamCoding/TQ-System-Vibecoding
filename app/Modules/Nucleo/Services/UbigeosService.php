<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Services;

use App\Modules\Nucleo\Enums\OrigenUbigeo;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Ubigeos\DiferenciaUbigeos;
use App\Modules\Nucleo\Ubigeos\DistritoUbigeo;
use App\Modules\Nucleo\Ubigeos\PadronUbigeos;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Actualización de ubigeos contra la lista vigente del INEI, sobre la base (no el CSV): lo usa el botón
 * de Configuración. La descarga va siempre antes y fuera de la transacción (convención: nada externo
 * dentro de una transacción).
 */
final class UbigeosService
{
    public function __construct(private readonly PadronUbigeos $padron) {}

    /**
     * @return list<DistritoUbigeo>
     */
    public function descargar(): array
    {
        return $this->padron->descargar((string) config('nucleo.ubigeos.url'));
    }

    /**
     * Qué cambiaría: nuevos, renombrados, retirados y adoptados (ver comparar()).
     *
     * @param  list<DistritoUbigeo>  $descargados
     */
    public function diferencias(array $descargados): DiferenciaUbigeos
    {
        return $this->comparar(Ubigeo::query()->get()->keyBy('codigo'), $descargados);
    }

    /**
     * Crea los nuevos, corrige nombres, adopta los manuales que el INEI ya publica y desactiva los suyos
     * que ya no están. Nunca borra ni reactiva: lo desactivado sigue así hasta que una persona lo active.
     *
     * @param  list<DistritoUbigeo>  $descargados
     */
    public function aplicar(array $descargados): DiferenciaUbigeos
    {
        return DB::transaction(function () use ($descargados): DiferenciaUbigeos {
            // Primero se bloquea y después se compara: el resumen corresponde a lo que se aplica.
            $existentes = Ubigeo::query()->lockForUpdate()->get()->keyBy('codigo');
            $diferencia = $this->comparar($existentes, $descargados);

            foreach ($diferencia->nuevos as $distrito) {
                (new Ubigeo(['codigo' => $distrito->codigo, 'origen' => OrigenUbigeo::Inei]))->fill($this->nombres($distrito))->save();
            }

            foreach (array_column($diferencia->renombrados, 'ahora') as $distrito) {
                $existentes->get($distrito->codigo)?->fill([...$this->nombres($distrito), 'origen' => OrigenUbigeo::Inei])->save();
            }

            foreach ($diferencia->adoptados as $distrito) {
                $existentes->get($distrito->codigo)?->fill(['origen' => OrigenUbigeo::Inei])->save();
            }

            foreach ($diferencia->retirados as $distrito) {
                $existentes->get($distrito->codigo)?->update(['es_activo' => false]);
            }

            return $diferencia;
        });
    }

    /**
     * Contra todas las filas, activas o no (plan 1.2b, E7):
     * - nuevos: códigos que la base no tiene;
     * - renombrados: cambió algún nombre (conserva si está activo; si era manual, pasa a INEI);
     * - retirados: solo los del INEI que siguen activos; un manual no está en el padrón y no se apaga;
     * - adoptados: manuales que el INEI publica con el mismo nombre.
     *
     * @param  Collection<string, Ubigeo>  $existentes
     * @param  list<DistritoUbigeo>  $descargados
     */
    private function comparar(Collection $existentes, array $descargados): DiferenciaUbigeos
    {
        $base = $this->padron->comparar(
            $existentes->sortKeys()->map(fn (Ubigeo $u): DistritoUbigeo => new DistritoUbigeo($u->codigo, $u->departamento, $u->provincia, $u->distrito))->values()->all(),
            $descargados,
        );

        $renombrados = array_column(array_column($base->renombrados, 'ahora'), 'codigo');
        $publicados = array_column($descargados, null, 'codigo');

        return new DiferenciaUbigeos(
            $base->nuevos,
            $base->renombrados,
            array_values(array_filter(
                $base->retirados,
                fn (DistritoUbigeo $d): bool => $existentes[$d->codigo]->origen === OrigenUbigeo::Inei && $existentes[$d->codigo]->es_activo,
            )),
            array_values(array_map(
                fn (Ubigeo $u): DistritoUbigeo => $publicados[$u->codigo],
                $existentes->filter(fn (Ubigeo $u): bool => $u->origen === OrigenUbigeo::Manual
                    && isset($publicados[$u->codigo])
                    && ! in_array($u->codigo, $renombrados, true))->sortKeys()->all(),
            )),
        );
    }

    /**
     * @return array{departamento: string, provincia: string, distrito: string}
     */
    private function nombres(DistritoUbigeo $distrito): array
    {
        return ['departamento' => $distrito->departamento, 'provincia' => $distrito->provincia, 'distrito' => $distrito->distrito];
    }
}
