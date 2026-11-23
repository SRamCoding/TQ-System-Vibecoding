<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Crm\Licitaciones\Services\ConvocatoriasSeaceService;
use App\Modules\Crm\Licitaciones\Services\FiltroConvocatorias;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Observación 7.5 (ficha t1-licitaciones, MV-078): los casos de la bandeja con detalle, repartidos en las tres líneas.
 * - La cámara frigorífica del camal: coincide con «cámara» pero la deja fuera la palabra negativa (para «¿Por qué no
 *   entró?»).
 * - Tres procesos anteriores de la Municipalidad Provincial de Valle Norte con su ganador (dos ganados por Techquk), para
 *   la tabla de ganadores.
 * - Dos más descartadas, una con responsable y etiqueta, y dos «mías» (del usuario de desarrollo).
 * Idempotente: si la cámara frigorífica ya está, no hace nada.
 */
final class BandejaCasosSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('convocatorias_seace')->where('ocid', 'ocds-dgv273-1020')->exists() || ! DB::table('convocatorias_seace')->exists()) {
            return;
        }

        $s = app(ConvocatoriasSeaceService::class);
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $ruc = fn (string $base): string => $base.DocumentoIdentidad::digitoVerificadorRuc($base);
        $mpvn = $ruc('2069910001');
        $release = function (string $ocid, string $nom, string $entidad, string $rucE, string $objeto, string $metodo, string $valor, CarbonImmutable $pub, ?array $award = null) {
            $r = [
                'ocid' => "ocds-dgv273-{$ocid}", 'date' => $pub->setTime(9, 0)->format('Y-m-d\TH:i:s.uP'), 'buyer' => ['id' => "PE-RUC-{$rucE}", 'name' => $entidad],
                'parties' => [['id' => "PE-RUC-{$rucE}", 'name' => $entidad, 'identifier' => ['scheme' => 'PE-RUC', 'id' => $rucE], 'address' => ['region' => 'Valle Norte'], 'roles' => ['buyer']]],
                'tender' => ['id' => $nom, 'title' => $nom, 'description' => $objeto, 'procurementMethodDetails' => $metodo, 'status' => $award === null ? 'active' : 'complete',
                    'value' => ['amount' => (float) $valor, 'currency' => 'PEN'], 'datePublished' => $pub->format('Y-m-d').'T09:00:00-05:00',
                    'tenderPeriod' => ['endDate' => $pub->addDays(15)->format('Y-m-d').'T23:59:00-05:00'], 'awardPeriod' => ['startDate' => $pub->addDays(22)->format('Y-m-d').'T10:00:00-05:00'],
                    'documents' => [['url' => 'https://prod2.seace.gob.pe/seacebus-uiwd-pub/buscadorPublico/buscadorPublico.xhtml', 'documentType' => 'biddingDocuments']]],
            ];

            if ($award !== null) {
                $r['awards'] = [['status' => 'active', 'value' => ['amount' => (float) $award[1], 'currency' => 'PEN'], 'suppliers' => [['id' => 'PE-RUC-'.$award[2], 'name' => $award[0]]]]];
            }

            return $r;
        };

        $s->importar((string) json_encode(['releases' => [
            // Fuera por palabra negativa.
            $release('1020', 'AS-SM-9-2026-MPVN-1', 'Municipalidad Provincial de Valle Norte', $mpvn, 'Adquisición de cámara frigorífica para el camal municipal', 'Adjudicación Simplificada', '64200', $hoy->subDays(3)),
            // Procesos anteriores con ganador (de lo guardado del OECE).
            $release('0901', 'AS-SM-22-2025-MPVN-1', 'Municipalidad Provincial de Valle Norte', $mpvn, 'Cámaras de videovigilancia en el centro histórico', 'Adjudicación Simplificada', '652750', CarbonImmutable::parse('2025-06-10'), ['Techquk S.A.C.', '612300', '20699000010']),
            $release('0902', 'AS-SM-30-2025-MPVN-1', 'Municipalidad Provincial de Valle Norte', $mpvn, 'Red de fibra óptica municipal entre sedes', 'Adjudicación Simplificada', '280925', CarbonImmutable::parse('2025-09-02'), ['Redes Andinas S.A.C.', '248900', '20481234567']),
            $release('0903', 'AS-SM-12-2024-MPVN-1', 'Municipalidad Provincial de Valle Norte', $mpvn, 'Monitoreo con cámaras de los mercados municipales', 'Adjudicación Simplificada', '196900', CarbonImmutable::parse('2024-08-20'), ['Techquk S.A.C.', '187450', '20699000010']),
            // Dos que entran y se descartan.
            $release('1021', 'AS-SM-41-2026-GRVN-1', 'Gobierno Regional de Valle Norte', $ruc('2069910005'), 'Servicio de soporte del software de gestión documental en 40 sedes', 'Adjudicación Simplificada', '186000', $hoy->subDays(5)),
            $release('1022', 'CP-SM-14-2026-UNVN-1', 'Universidad Nacional del Valle Norte', $ruc('2069910007'), 'Enlaces inalámbricos y wifi para el campus de la sede rural', 'Concurso Público', '2950000', $hoy->subDays(4)),
        ]]));

        $id = fn (string $ocid): int => (int) DB::table('convocatorias_seace')->where('ocid', "ocds-dgv273-{$ocid}")->value('id');
        $s->descartar($id('1021'), 'Plazo imposible');
        $s->descartar($id('1022'), 'Monto muy alto para nosotros');

        $dev = User::query()->where('email', 'dev@techquk.test')->value('id') ?? User::query()->where('es_sistema', false)->orderBy('id')->value('id');
        $valeria = User::query()->where('name', 'like', 'Valeria%')->value('id') ?? $dev;
        $s->asignar($id('1001'), $valeria);
        $s->etiquetar($id('1001'), ['Visita técnica']);
        $s->asignar($id('1005'), $dev);
        $s->asignar($id('1006'), $dev);

        app(FiltroConvocatorias::class)->reaplicar();

        foreach (DB::table('convocatorias_seace')->pluck('id') as $c) {
            $s->priorizar((int) $c);
        }
    }
}
