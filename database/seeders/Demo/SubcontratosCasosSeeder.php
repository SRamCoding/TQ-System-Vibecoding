<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Erp\Valorizaciones\Services\SubcontratosService;
use App\Modules\Erp\Valorizaciones\Services\ValorizacionesService;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Services\TipoCambioService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Observaciones 6, punto 12 (ficha t4-valorizaciones): los casos de Subcontratos, junto al SC-2026-0001 del v4 (que
 * sigue igual: val. 2 por aprobar y, con ella, la alarma «le pagas 75 %, cobraste 41 %»). Uno con saldo por pagar, uno
 * con la alarma sin nada pendiente, uno al 100 % listo para liberar la retención (sin valorizar al cliente) y uno terminado con la retención
 * devuelta. Además, el permiso de «Liberar retención» al rol `desarrollo`. Idempotente: si ya están, no hace nada.
 */
final class SubcontratosCasosSeeder extends Seeder
{
    public function run(): void
    {
        Permission::findOrCreate(SubcontratosService::PERMISO_LIBERAR, 'web');
        Role::query()->where('name', 'desarrollo')->first()?->givePermissionTo(SubcontratosService::PERMISO_LIBERAR);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (DB::table('subcontratos')->where('subcontratista', 'Enlaces y Torres del Pacífico S.A.C.')->exists() || ! DB::table('subcontratos')->exists()) {
            return;
        }

        auth()->setUser(User::query()->where('es_sistema', false)->whereDoesntHave('roles', fn ($q) => $q->where('name', SeguridadService::ROL_GERENCIA_GENERAL))->orderBy('id')->firstOrFail());
        $v = app(ValorizacionesService::class);
        $pc = fn (int $proyecto, string $codigo): int => (int) DB::table('partidas_control')->where('proyecto_id', $proyecto)->where('codigo', $codigo)->value('id');
        $p8 = (int) DB::table('proyectos')->where('nombre', 'like', 'Red de datos y enlaces%')->value('id');
        $p6 = (int) DB::table('proyectos')->where('nombre', 'like', 'Sistema de información hospitalaria%')->value('id');
        $plaza = (int) DB::table('proyectos')->where('nombre', 'like', '%Plaza de Armas%')->value('id');

        // Valorizaciones: [periodo, avance %, día en que se registró, estado final].
        $caso = function (string $alta, int $proyecto, string $quien, string $objeto, string $adelanto, string $retencion, array $alcance, array $vals) use ($v): int {
            $sc = $this->en($alta, fn () => $v->crearSubcontrato($proyecto, $quien, $objeto, $adelanto, $retencion, $alcance));

            foreach ($vals as [$periodo, $avance, $dia, $estado]) {
                $this->en($dia, function () use ($v, $sc, $periodo, $avance, $estado): void {
                    $val = $v->valorizarSubcontrato($sc->id, $periodo, $avance);

                    if ($estado !== 'por_aprobar') {
                        $v->aprobarSubcontrato($val->id);
                    }

                    if ($estado === 'pagada') {
                        $val->update(['estado' => 'pagada']);
                    }
                });
            }

            return $sc->id;
        };

        // Con saldo por pagar: 55 % valorizado contra 70 % cobrado al cliente en la 02.01; la val. 2 aprobada, sin pagar
        // (15,000.00 − 3,000.00 − 750.00 = 11,250.00 por pagar).
        $caso('2026-08-10', $p8, 'Enlaces y Torres del Pacífico S.A.C.', 'Torres y radioenlaces a las sedes descentralizadas', '20', '5',
            [$pc($p8, 'PC-02.01') => '60000.00'], [['2026-09', '30', '2026-09-29', 'pagada'], ['2026-10', '25', '2026-10-30', 'aprobada']]);

        // La alarma sin nada pendiente: le pagas 65 % de la climatización y el UPS; al cliente se le cobró 30 % de la 03.01.
        $caso('2026-08-18', $p8, 'Climatización y Energía Andina S.A.C.', 'Climatización de precisión y UPS del centro de datos', '10', '5',
            [$pc($p8, 'PC-03.01') => '36000.00'], [['2026-09', '30', '2026-09-28', 'pagada'], ['2026-10', '35', '2026-10-29', 'pagada']]);

        // Al 100 % y listo para liberar la retención (160.00): sin valorizar al cliente (la Plaza de Armas aún no tiene
        // valorizaciones); la última aprobada queda por pagar.
        $caso('2026-10-05', $plaza, 'Cimentaciones Rápidas del Norte E.I.R.L.', 'Cimentación de los ocho postes de las cámaras', '0', '5',
            [$pc($plaza, 'PC-02.02') => '3200.00'], [['2026-10', '60', '2026-10-30', 'pagada'], ['2026-11', '40', '2026-11-18', 'aprobada']]);

        // Terminado con la retención devuelta: 45,000.00 sin adelanto, retención 5 % (2,250.00), conformidad del 15/07.
        $id = $caso('2026-04-20', $p6, 'Migraciones de Datos Clínicos S.A.C.', 'Migración de historias clínicas del sistema anterior', '0', '5',
            [$pc($p6, 'PC-01.02') => '45000.00'], [['2026-05', '50', '2026-05-29', 'pagada'], ['2026-06', '50', '2026-06-30', 'pagada']]);
        $this->en('2026-07-15', fn () => app(SubcontratosService::class)->liberarRetencion($id, CarbonImmutable::parse('2026-07-15'), 'Migración validada por el área de Estadística del hospital; sin observaciones.'));
        DB::table('subcontratos')->where('id', $id)->update(['retencion_pagada' => true]);

        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }

    private function en(string $dia, callable $paso): mixed
    {
        $momento = CarbonImmutable::parse($dia, 'America/Lima')->setTime(10, 0);
        Carbon::setTestNow($momento);
        CarbonImmutable::setTestNow($momento);
        $tipos = app(TipoCambioService::class);

        if (! $tipos->hayDe($momento->startOfDay())) {
            $tipos->registrar('USD', $momento->startOfDay(), '3.7400', '3.7500', OrigenTipoCambio::Manual);
        }

        return $paso();
    }
}
