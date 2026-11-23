<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Crm\Licitaciones\Services\ConvocatoriasSeaceService;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Observación 8.9 (ficha t1-licitaciones, MV-089): una convocatoria de cámaras en Cusco que coincide con Videovigilancia
 * pero queda fuera porque esa línea solo busca en Lambayeque, Piura, La Libertad, Cajamarca y Amazonas (para «¿Por qué
 * no entró?»: AS-SM-12-2026-MDW-1). Idempotente.
 */
final class RegionesCasosSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('convocatorias_seace')->where('ocid', 'ocds-dgv273-1030')->exists() || ! DB::table('seace_lineas')->exists()) {
            return;
        }

        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $ruc = '2049100'.'101';
        $ruc .= DocumentoIdentidad::digitoVerificadorRuc($ruc);
        $entidad = 'Municipalidad Distrital de Wanchaq';
        app(ConvocatoriasSeaceService::class)->importar((string) json_encode(['releases' => [[
            'ocid' => 'ocds-dgv273-1030', 'date' => $hoy->subDays(2)->setTime(9, 0)->format('Y-m-d\TH:i:s.uP'), 'buyer' => ['id' => "PE-RUC-{$ruc}", 'name' => $entidad],
            'parties' => [['id' => "PE-RUC-{$ruc}", 'name' => $entidad, 'identifier' => ['scheme' => 'PE-RUC', 'id' => $ruc], 'address' => ['region' => 'CUSCO', 'department' => 'CUSCO'], 'roles' => ['buyer']]],
            'tender' => ['id' => 'AS-SM-12-2026-MDW-1', 'title' => 'AS-SM-12-2026-MDW-1', 'description' => 'Adquisición e instalación de cámaras de videovigilancia para parques del distrito',
                'procurementMethodDetails' => 'Adjudicación Simplificada', 'status' => 'active', 'value' => ['amount' => 184000.0, 'currency' => 'PEN'],
                'tenderPeriod' => ['endDate' => $hoy->addDays(14)->format('Y-m-d').'T23:59:00-05:00'], 'awardPeriod' => ['startDate' => $hoy->addDays(21)->format('Y-m-d').'T10:00:00-05:00']],
        ]]]));
    }
}
