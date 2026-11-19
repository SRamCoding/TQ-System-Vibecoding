<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

use App\Modules\Crm\Cuentas\Actividad\AporteOportunidadesAbiertas;
use App\Modules\Crm\Cuentas\Actividad\DatoActividad;
use App\Modules\Crm\Cuentas\Actividad\DatosActividadCuenta;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use Illuminate\Database\Query\Builder;

/**
 * Lo que Oportunidades le dice a Cuentas (plan 2.21, D13): cuántas abiertas tiene cada cuenta (una consulta por página),
 * la subconsulta para filtrar «con abiertas» y el impedimento para desactivar. Nombra solo su tabla (D13.4).
 */
final class AporteOportunidadesACuentas implements AporteOportunidadesAbiertas
{
    public function datos(): array
    {
        return [DatoActividad::OportunidadesAbiertas];
    }

    public function leer(array $cuentaIds): array
    {
        if ($cuentaIds === []) {
            return [];
        }

        return Oportunidad::query()->abiertas()->whereIn('cuenta_id', $cuentaIds)
            ->selectRaw('cuenta_id, count(*) AS abiertas')->groupBy('cuenta_id')->toBase()->get()
            ->mapWithKeys(fn (object $fila): array => [(int) $fila->cuenta_id => new DatosActividadCuenta(oportunidadesAbiertas: (int) $fila->abiertas)])
            ->all();
    }

    public function impedimentosParaDesactivar(int $cuentaId): array
    {
        $abiertas = Oportunidad::query()->abiertas()->where('cuenta_id', $cuentaId)->count();

        return match ($abiertas) {
            0 => [],
            1 => ['1 oportunidad abierta'],
            default => ["{$abiertas} oportunidades abiertas"],
        };
    }

    public function cuentasConAbiertas(): Builder
    {
        return Oportunidad::query()->abiertas()->select('oportunidades.cuenta_id')->toBase();
    }
}
