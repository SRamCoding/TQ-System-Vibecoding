<?php

declare(strict_types=1);

namespace App\Modules\Crm\Actividades\Services;

use App\Modules\Crm\Cuentas\Actividad\AporteUltimoContacto;
use App\Modules\Crm\Cuentas\Actividad\DatoActividad;
use App\Modules\Crm\Cuentas\Actividad\DatosActividadCuenta;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** MVP rápido (t1-agenda): las actividades le dicen a Cuentas su último y su próximo contacto (plan 2.20, D11). */
final class AporteActividadesACuentas implements AporteUltimoContacto
{
    public function datos(): array
    {
        return [DatoActividad::UltimoContacto, DatoActividad::ProximoContacto];
    }

    public function leer(array $cuentaIds): array
    {
        if ($cuentaIds === []) {
            return [];
        }

        return DB::table('actividades')->whereIn('cuenta_id', $cuentaIds)
            ->selectRaw('cuenta_id, MAX(fecha) FILTER (WHERE es_realizada) AS ultimo, MIN(fecha) FILTER (WHERE NOT es_realizada) AS proximo')
            ->groupBy('cuenta_id')->get()
            ->mapWithKeys(fn (object $f): array => [(int) $f->cuenta_id => new DatosActividadCuenta(
                ultimoContacto: $f->ultimo === null ? null : CarbonImmutable::parse($f->ultimo)->setTimezone('America/Lima')->startOfDay(),
                proximoContacto: $f->proximo === null ? null : CarbonImmutable::parse($f->proximo)->setTimezone('America/Lima')->startOfDay(),
            )])->all();
    }

    public function impedimentosParaDesactivar(int $cuentaId): array
    {
        return [];
    }

    public function ultimosContactos(): Builder
    {
        return DB::table('actividades')->where('es_realizada', true)
            ->selectRaw("cuenta_id, (MAX(fecha) AT TIME ZONE 'America/Lima')::date AS ultimo_contacto")->groupBy('cuenta_id');
    }
}
