<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Crm\Licitaciones\Services\ConvocatoriasSeaceService;
use App\Modules\Nucleo\Parties\DocumentoIdentidad;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Observación 7.4 (ficha t1-licitaciones): los casos de la lectura del SEACE.
 * - Prioritarias marcadas (valor desde S/ 500,000 o entidad que ya es cuenta).
 * - Una lectura terminada hace 20 minutos: al entrar a la bandeja se lee otra sola, y en local trae la convocatoria que
 *   la demo dejó «por publicar» (aparece en la lista sin recargar).
 * - La registrada del Gobierno Regional (LP-SM-12-2026-GRVN-1) llega en una versión nueva con la presentación movida
 *   cinco días: queda anotado el cambio y el aviso en Mien.
 * Idempotente: si la registrada ya tiene su cambio anotado, no hace nada.
 */
final class ConvocatoriasCasosSeeder extends Seeder
{
    public function run(): void
    {
        $s = app(ConvocatoriasSeaceService::class);
        $registrada = DB::table('convocatorias_seace')->where('ocid', 'ocds-dgv273-1002')->first();

        if ($registrada === null || $registrada->cambios !== null) {
            return;
        }

        foreach (DB::table('convocatorias_seace')->pluck('id') as $id) {
            $s->priorizar((int) $id);
        }

        $ahora = Calendario::ahora();
        $ruc = fn (string $base): string => $base.DocumentoIdentidad::digitoVerificadorRuc($base);
        $release = fn (string $ocid, string $nom, string $entidad, string $rucE, string $titulo, string $metodo, string $valor, CarbonImmutable $fecha, CarbonImmutable $pres) => [
            'ocid' => "ocds-dgv273-{$ocid}", 'date' => $fecha->format('Y-m-d\TH:i:s.uP'), 'buyer' => ['id' => "PE-RUC-{$rucE}", 'name' => $entidad],
            'parties' => [['id' => "PE-RUC-{$rucE}", 'name' => $entidad, 'identifier' => ['scheme' => 'PE-RUC', 'id' => $rucE], 'address' => ['region' => 'Valle Norte', 'department' => 'LAMBAYEQUE'], 'roles' => ['buyer']]],
            'tender' => ['id' => $nom, 'title' => $nom, 'description' => $titulo, 'procurementMethodDetails' => $metodo, 'status' => 'active', 'value' => ['amount' => (float) $valor, 'currency' => 'PEN'],
                'tenderPeriod' => ['endDate' => $pres->format('Y-m-d').'T23:59:00-05:00'], 'awardPeriod' => ['startDate' => $pres->addDays(7)->format('Y-m-d').'T10:00:00-05:00'],
                'documents' => [['url' => 'https://prod2.seace.gob.pe/seacebus-uiwd-pub/buscadorPublico/buscadorPublico.xhtml', 'documentType' => 'biddingDocuments']]],
        ];

        // La registrada cambió su fecha de presentación: llega como versión nueva (dispara «cambió» y su aviso).
        $nueva = $release('1002', $registrada->nomenclatura, $registrada->entidad, (string) $registrada->ruc_entidad, $registrada->objeto, 'Licitación Pública', (string) $registrada->valor_referencial,
            $ahora->subHours(2), CarbonImmutable::parse((string) $registrada->presentacion)->addDays(5));
        $s->guardar($nueva);

        // Lo que «publicará» el OECE en la próxima lectura (solo local): aparece sola al entrar.
        Storage::disk('local')->put(ConvocatoriasSeaceService::DEMO_POR_PUBLICAR, (string) json_encode(['releases' => [
            $release('1010', 'AS-SM-33-2026-MPVN-1', 'Municipalidad Provincial de Valle Norte', $ruc('2069910001'), 'Adquisición e instalación de cámaras de videovigilancia y centro de monitoreo para el terminal terrestre',
                'Adjudicación Simplificada', '418000', $ahora->subMinutes(30), $ahora->addDays(12)),
        ]]));

        DB::table('seace_lecturas')->insert(['inicio' => $ahora->subMinutes(21), 'fin' => $ahora->subMinutes(20), 'es_ok' => true, 'leidas' => 9, 'nuevas' => 9, 'a_bandeja' => 6, 'origen' => 'programada']);
    }
}
