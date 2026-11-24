<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Crm\Licitaciones\Services\ConvocatoriasSeaceService;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Observación 8.12 (ficha t1-licitaciones, MV-092): cada entidad de la demo tiene 2 o 3 procesos anteriores parecidos con
 * su ganador (algunos de Techquk) y descuentos distintos. Observación 9.7 (MV-101): van marcados `es_demo`: solo los usan
 * las licitaciones de demo de Oportunidades («¿Cuánto bajar?», ganadores); nunca la bandeja de Convocatorias ni «Buscar
 * en todo el SEACE». Idempotente.
 */
final class GanadoresCasosSeeder extends Seeder
{
    private const string TECHQUK = '20699000010';

    public function run(): void
    {
        if (DB::table('convocatorias_seace')->where('ocid', 'ocds-dgv273-0910')->exists()) {
            return;
        }

        $ruc = fn (string $base): string => $base.DocumentoIdentidad::digitoVerificadorRuc($base);
        // Entidad => [RUC base, departamento, [objeto, año, ganador, RUC del ganador, valor referencial, adjudicado]].
        $casos = [
            'Municipalidad Provincial de Valle Norte' => ['2069910001', 'LAMBAYEQUE', [
                ['Cámaras de videovigilancia del centro histórico', 2025, 'Techquk S.A.C.', self::TECHQUK, '310000', '289000'],
                ['Centro de monitoreo del serenazgo', 2024, 'Techquk S.A.C.', self::TECHQUK, '450000', '418500'],
                ['Red de datos del palacio municipal', 2025, 'Redes Andinas S.A.C.', '20481234567', '120000', '106800'],
            ]],
            'Gobierno Regional de Valle Norte' => ['2069910005', 'LAMBAYEQUE', [
                ['Red de datos de la sede regional', 2025, 'Redes Andinas S.A.C.', '20481234567', '520000', '471500'],
                ['Enlaces de fibra óptica entre sedes provinciales', 2024, 'Techquk S.A.C.', self::TECHQUK, '402000', '384000'],
                ['Cableado estructurado del archivo regional', 2025, 'Conecta Norte E.I.R.L.', '20601234561', '176000', '158000'],
            ]],
            'Hospital Regional de Valle Norte' => ['2069910004', 'LAMBAYEQUE', [
                ['Software de citas y gestión de consultorios', 2025, 'Sistemas Médicos del Pacífico S.A.C.', '20555123451', '238000', '214000'],
                ['Mantenimiento del sistema de información hospitalaria', 2024, 'Techquk S.A.C.', self::TECHQUK, '99000', '96500'],
            ]],
            'Municipalidad Distrital de Las Palmeras' => ['2069960001', 'LAMBAYEQUE', [
                ['Wifi gratuito en el parque principal', 2025, 'Conecta Norte E.I.R.L.', '20601234561', '74000', '61000'],
                ['Cámaras de videovigilancia del mercado', 2024, 'Seguridad Norte S.A.C.', '20480001112', '97000', '88000'],
            ]],
            'Dirección Regional de Salud Valle Norte' => ['2069960002', 'LAMBAYEQUE', [
                ['Servidores para el centro de datos regional', 2025, 'Data Perú Tech S.A.C.', '20512345671', '590000', '512000'],
                ['Switch y red de datos de las microrredes', 2024, 'Techquk S.A.C.', self::TECHQUK, '151000', '143000'],
                ['Licencias de software de virtualización de servidores', 2025, 'Andes Tech E.I.R.L.', '20600011122', '92000', '87500'],
            ]],
            'Universidad Nacional del Valle Norte' => ['2069910007', 'LAMBAYEQUE', [
                ['Cableado estructurado de la biblioteca central', 2025, 'Techquk S.A.C.', self::TECHQUK, '214000', '198000'],
                ['Wifi y enlaces inalámbricos del campus', 2024, 'Redes Andinas S.A.C.', '20481234567', '301000', '266000'],
            ]],
            'Unidad de Gestión Educativa Local Valle Norte' => ['2069910002', 'LAMBAYEQUE', [
                ['Red de datos de instituciones educativas', 2025, 'Conecta Norte E.I.R.L.', '20601234561', '150000', '132000'],
                ['Cámaras de videovigilancia para colegios emblemáticos', 2024, 'Techquk S.A.C.', self::TECHQUK, '184000', '175000'],
            ]],
            'Municipalidad Distrital de Wanchaq' => ['2049100101', 'CUSCO', [
                ['Cámaras de videovigilancia de la plaza principal', 2024, 'Seguridad Sur S.A.C.', '20490001112', '158000', '140000'],
                ['Red de datos del palacio municipal', 2025, 'Cusco Redes E.I.R.L.', '20491234561', '101000', '92000'],
            ]],
        ];

        $s = app(ConvocatoriasSeaceService::class);
        $n = 10;

        foreach ($casos as $entidad => [$base, $departamento, $procesos]) {
            $rucE = $ruc($base);

            foreach ($procesos as [$objeto, $anio, $ganador, $rucG, $vr, $adjudicado]) {
                $ocid = 'ocds-dgv273-09'.str_pad((string) $n++, 2, '0', STR_PAD_LEFT);
                $fecha = sprintf('%d-%02d-15', $anio, 3 + ($n % 8));
                $r = [
                    'ocid' => $ocid, 'date' => $fecha.'T09:00:00.000000-05:00', 'buyer' => ['id' => "PE-RUC-{$rucE}", 'name' => $entidad],
                    'parties' => [['id' => "PE-RUC-{$rucE}", 'name' => $entidad, 'identifier' => ['scheme' => 'PE-RUC', 'id' => $rucE], 'address' => ['region' => $departamento, 'department' => $departamento], 'roles' => ['buyer']]],
                    'tender' => ['id' => sprintf('AS-SM-%d-%d-ANT-1', $n, $anio), 'title' => sprintf('AS-SM-%d-%d-ANT-1', $n, $anio), 'description' => $objeto, 'procurementMethodDetails' => 'Adjudicación Simplificada',
                        'status' => 'complete', 'datePublished' => $fecha.'T09:00:00-05:00', 'value' => ['amount' => (float) $vr, 'currency' => 'PEN']],
                    'awards' => [['status' => 'active', 'value' => ['amount' => (float) $adjudicado, 'currency' => 'PEN'], 'suppliers' => [['id' => "PE-RUC-{$rucG}", 'name' => $ganador]]]],
                ];
                DB::table('convocatorias_seace')->insert(['ocid' => $ocid, ...$s->datosLivianos($r), 'estado' => 'nueva', 'es_historico' => true, 'es_demo' => true, 'en_bandeja' => false, 'prioritaria' => false, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }
}
