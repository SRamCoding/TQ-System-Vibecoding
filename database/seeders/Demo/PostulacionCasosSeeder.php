<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Crm\Licitaciones\Enums\EstadoLicitacion;
use App\Modules\Crm\Licitaciones\Models\Licitacion;
use App\Modules\Erp\Presupuestos\Services\ArmadoPresupuestoService;
use App\Modules\Erp\Presupuestos\Services\DatosPresupuesto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Observación 7.3 (ficha t1-licitaciones, MV-076): los casos de la postulación por pasos, con fechas relativas a hoy.
 * - LIC-2026-0005 pasa a ser la del lienzo: «Sistema de videovigilancia urbana con 48 cámaras IP», valor referencial
 *   S/ 1,284,500.00, presentación en 3 días, en el paso Expediente con documentos en todos los estados y la oferta lista
 *   (S/ 1,198,000.00) desde su presupuesto.
 * - LIC-2026-0007 ya presentada, con su hora y su constancia.
 * - LIC-2026-0008 presentada y con una subsanación pedida (plazo en 2 días).
 * Idempotente: si la del lienzo ya tiene su oferta, no hace nada.
 */
final class PostulacionCasosSeeder extends Seeder
{
    public function run(): void
    {
        $lienzo = Licitacion::query()->where('codigo', 'LIC-2026-0005')->first();

        if ($lienzo === null || $lienzo->monto_ofertado !== null) {
            return;
        }

        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $usuarios = User::query()->where('es_sistema', false)->where('es_activo', true)->orderBy('id')->get()->keyBy('name');
        $quien = fn (string $nombre): ?int => $usuarios->first(fn (User $u): bool => str_contains($u->name, $nombre))?->id;
        $lv = $quien('Valeria') ?? $usuarios->first()?->id;
        $mr = $quien('Rosa') ?? $lv;
        $jc = $quien('Diego') ?? $lv;
        auth()->setUser($usuarios->first(fn (User $u): bool => $u->id === $lv) ?? $usuarios->first());

        $this->delLienzo($lienzo, $hoy, $lv, $mr, $jc);
        $this->presentada($hoy, $lv);
        $this->conSubsanacion($hoy, $jc);
    }

    private function delLienzo(Licitacion $l, CarbonImmutable $hoy, ?int $lv, ?int $mr, ?int $jc): void
    {
        $l->update(['objeto' => 'Sistema de videovigilancia urbana con 48 cámaras IP', 'valor_referencial' => '1284500.00', 'nomenclatura' => 'LP-SM-4-2026-MPVN-2',
            'tipo_procedimiento' => 'LP', 'responsable_user_id' => $lv,
            'registrado_seace_el' => $hoy->subDays(11)->toDateString(), 'consultas_absueltas_el' => $hoy->subDays(3)->toDateString(), 'bases_integradas_el' => $hoy->toDateString()]);
        DB::table('oportunidades')->where('id', $l->oportunidad_id)->update(['nombre' => 'Sistema de videovigilancia urbana con 48 cámaras IP', 'monto_estimado' => '1284500.00']);

        $fechas = ['Convocatoria' => -19, 'Consultas y observaciones' => -13, 'Absolución' => -3, 'Integración de bases' => 0, 'Presentación de ofertas' => 3, 'Buena pro' => 10];

        foreach ($fechas as $etapa => $dias) {
            $l->hitos()->where('etapa', $etapa)->update(['fecha' => $hoy->addDays($dias)->toDateString(), 'es_cumplido' => $dias <= 0]);
        }

        $l->consultas()->delete();
        $l->consultas()->create(['tipo' => 'consulta', 'numeral' => 'Capítulo III, numeral 3.1', 'estado' => 'absuelta', 'origen' => 'ia', 'fecha' => $hoy->subDays(14)->toDateString(), 'enviada_el' => $hoy->subDays(13)->toDateString(),
            'pregunta' => 'Se solicita confirmar si las cámaras pueden ser de 4 MP con lente varifocal, equivalentes a las de 5 MP fijas pedidas en las especificaciones técnicas.',
            'respuesta' => 'Se acepta: cámaras de 4 MP con lente varifocal, siempre que cumplan el resto de especificaciones.']);
        $l->consultas()->create(['tipo' => 'observacion', 'numeral' => 'Capítulo II, numeral 2.2.1.2', 'estado' => 'absuelta', 'origen' => 'manual', 'fecha' => $hoy->subDays(14)->toDateString(), 'enviada_el' => $hoy->subDays(13)->toDateString(),
            'pregunta' => 'Se observa que la experiencia del postor exigida (tres veces el valor referencial) restringe la participación; se solicita reducirla a una vez el valor referencial.',
            'respuesta' => 'Se acoge parcialmente: la experiencia exigida queda en dos veces el valor referencial.']);

        $l->documentos()->delete();
        $documentos = [
            ['Anexo 1 · Declaración jurada de datos del postor', 'firmado', $lv, true, null],
            ['Anexo 2 · Pacto de integridad', 'firmado', $lv, true, null],
            ['Anexo 3 · Declaración de cumplimiento de especificaciones', 'listo', $mr, true, null],
            ['Constancia de RNP vigente', 'listo', $lv, false, $hoy->addDays(40)],
            ['Promesa de consorcio', 'no_aplica', null, false, null],
            ['Experiencia del postor · 3 contratos y conformidades', 'en_revision', $jc, false, null],
            ['Fichas técnicas de cámaras y NVR', 'falta', $mr, false, $hoy->addDays(2)],
            ['Carta de garantía del fabricante', 'falta', $jc, false, $hoy],
        ];

        foreach ($documentos as $i => [$nombre, $estado, $responsable, $firma, $vence]) {
            $l->documentos()->create(['nombre' => $nombre, 'origen' => $i < 5 ? 'ia' : 'manual', 'es_confirmado' => true, 'estado' => $estado, 'responsable_user_id' => $responsable,
                'requiere_firma' => $firma, 'vence' => $vence?->toDateString(), 'orden' => $i + 1]);
        }

        // La oferta sale de su presupuesto: costo directo S/ 601,900.00 + 29 % de gastos generales = S/ 776,451.00.
        $armado = app(ArmadoPresupuestoService::class);
        /** @var User $u */
        $u = auth()->user();
        $pre = $armado->crear(new DatosPresupuesto('Videovigilancia urbana con 48 cámaras IP', (int) DB::table('monedas')->where('codigo', 'PEN')->value('id'), '29', '10', usaApu: false, oportunidadId: $l->oportunidad_id), $u);
        $equipos = $armado->agregarTitulo($pre->id, null, '01', 'Equipamiento')->id;
        $obras = $armado->agregarTitulo($pre->id, null, '02', 'Instalación y centro de monitoreo')->id;
        $und = (int) DB::table('unidades_medida')->where('codigo', 'NIU')->value('id');
        $glb = (int) DB::table('unidades_medida')->where('codigo', 'GLB')->value('id');
        $armado->agregarPartida($pre->id, $equipos, '01.01', 'Cámaras IP 4 MP bullet', $und, '48', '1520.00');
        $armado->agregarPartida($pre->id, $equipos, '01.02', 'Grabador NVR 64 canales', $und, '2', '11400.00');
        $armado->agregarPartida($pre->id, $equipos, '01.03', 'Switch PoE 24 puertos', $und, '8', '3180.00');
        $armado->agregarPartida($pre->id, $obras, '02.01', 'Centro de monitoreo (videowall y estaciones)', $glb, '1', '149500.00');
        $armado->agregarPartida($pre->id, $obras, '02.02', 'Instalación, fibra y obras menores', $glb, '1', '331200.00');
        $l->update(['presupuesto_id' => $pre->id, 'monto_ofertado' => '1198000.00']);
    }

    private function presentada(CarbonImmutable $hoy, ?int $lv): void
    {
        $l = Licitacion::query()->where('codigo', 'LIC-2026-0007')->first();

        if ($l === null || $l->estado !== EstadoLicitacion::Presentada) {
            return;
        }

        $cuando = CarbonImmutable::parse($l->fecha_presentacion ?? $hoy->subDays(4))->setTime(18, 42);
        $constancia = "licitaciones/constancias/constancia-{$l->codigo}.pdf";
        Storage::disk('local')->put($constancia, '%PDF-1.4 Constancia de presentación de oferta (demo)');
        $l->update(['registrado_seace_el' => $cuando->subDays(15)->toDateString(), 'consultas_absueltas_el' => $cuando->subDays(8)->toDateString(), 'bases_integradas_el' => $cuando->subDays(5)->toDateString(),
            'paquete_armado_at' => $cuando->subHours(5), 'presentada_at' => $cuando, 'constancia_presentacion' => $constancia, 'responsable_user_id' => $l->responsable_user_id ?? $lv]);
        $l->documentos()->update(['estado' => 'firmado', 'es_listo' => true]);
    }

    private function conSubsanacion(CarbonImmutable $hoy, ?int $jc): void
    {
        $l = Licitacion::query()->where('codigo', 'LIC-2026-0008')->first();

        if ($l === null || $l->estado !== EstadoLicitacion::Presentada || $l->subsanaciones()->exists()) {
            return;
        }

        $presentada = CarbonImmutable::parse($l->fecha_presentacion ?? $hoy->subDays(6))->setTime(11, 15);
        $l->update(['registrado_seace_el' => $presentada->subDays(14)->toDateString(), 'bases_integradas_el' => $presentada->subDays(4)->toDateString(),
            'presentada_at' => $presentada, 'responsable_user_id' => $l->responsable_user_id ?? $jc]);
        $l->documentos()->update(['estado' => 'firmado', 'es_listo' => true]);
        $l->subsanaciones()->create(['pedido' => 'Presentar la carta de garantía del fabricante legalizada y la vigencia de poder del representante legal con menos de 30 días.',
            'pedida_el' => $hoy->subDay()->toDateString(), 'plazo' => $hoy->addDays(2)->toDateString()]);
    }
}
