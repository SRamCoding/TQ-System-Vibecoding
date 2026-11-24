<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Crm\Campanias\Models\Campania;
use App\Modules\Crm\Campanias\Models\Lead;
use App\Modules\Crm\Campanias\Services\CampaniasService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Observación 9.4 (ficha t1-campanias, MV-098): leads del mes de todos los canales con números distintos, para que la
 * vista Canales diga algo: WhatsApp trae muchos y califica poco; TikTok trae muchos, gasta y no gana nada; Referido trae
 * pocos pero gana. Los calificados se unen a oportunidades que ya existían (sin lead). Un contacto de la página web de
 * ayer avisa en Mien. Además completa el canal de las campañas y leads sembrados antes (observación 9.3). Idempotente.
 */
final class CanalesCasosSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(fn () => $this->sembrar());
    }

    private function sembrar(): void
    {
        $canal = DB::table('canales_lead')->pluck('id', 'codigo');

        if ($canal->isEmpty() || ! Campania::query()->exists()) {
            return;
        }

        $this->completarCanales($canal);

        if (Campania::query()->where('nombre', 'TikTok · cámaras para bodegas')->exists()) {
            return;
        }

        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $usuario = User::query()->where('email', 'dev@techquk.test')->first() ?? User::query()->where('es_sistema', false)->firstOrFail();
        auth()->setUser($usuario);
        $s = app(CampaniasService::class);
        $sede = (int) DB::table('party_sedes')->where('party_id', app(EmpresaService::class)->ficha()?->id ?? 0)->orderBy('id')->value('id') ?: null;
        $inicio = $hoy->startOfMonth();

        $instagram = Campania::query()->create(['nombre' => 'Instagram · colegios y academias', 'tipo' => 'redes', 'canal_lead_id' => $canal['instagram'], 'desde' => $inicio->toDateString(),
            'hasta' => $inicio->endOfMonth()->toDateString(), 'presupuesto' => '1500.00', 'objetivo' => 'Anuncios para colegios y academias: cámaras y wifi.', 'es_activa' => true]);
        $tiktok = Campania::query()->create(['nombre' => 'TikTok · cámaras para bodegas', 'tipo' => 'redes', 'canal_lead_id' => $canal['tiktok'], 'desde' => $inicio->toDateString(),
            'hasta' => $inicio->endOfMonth()->toDateString(), 'presupuesto' => '1200.00', 'objetivo' => 'Videos cortos de cámaras para bodegas y minimarkets.', 'es_activa' => true]);
        $referidos = Campania::query()->where('tipo', 'referidos')->first();
        $motivo = 'Gasto de campaña comercial';
        $s->registrarGasto($instagram->id, $sede, $inicio->addDays(4), '1150.00', 'Anuncios en Instagram (noviembre)', 'Meta Platforms Ireland Ltd.', $motivo);
        $s->registrarGasto($tiktok->id, $sede, $inicio->addDays(5), '1200.00', 'Anuncios en TikTok (noviembre)', 'TikTok Pte. Ltd.', $motivo);

        if ($referidos !== null) {
            $s->registrarGasto($referidos->id, $sede, $inicio->addDays(9), '600.00', 'Bonos de referidos de noviembre', 'Clientes referidores', $motivo);
        }

        // Oportunidades sin lead, por etapa (las ganadas con proyecto primero: su margen se puede calcular).
        $libres = DB::table('oportunidades as o')->join('estados as e', 'e.id', '=', 'o.estado_id')->leftJoin('proyectos as p', 'p.oportunidad_id', '=', 'o.id')
            ->whereNotExists(fn ($q) => $q->from('leads')->whereColumn('leads.oportunidad_id', 'o.id'))->where('e.codigo', '<>', 'anulada')
            ->orderByRaw('p.id is null')->orderBy('o.id')->get(['o.id', 'o.cuenta_id', 'o.nombre', 'e.codigo'])->groupBy('codigo');

        // [canal, total del mes, campaña, etapas de las oportunidades de sus calificados]
        $planes = [
            ['whatsapp', 26, null, ['calificada', 'negociacion', 'perdida']],
            ['tiktok', 21, $tiktok, ['perdida']],
            ['sitio_web', 11, null, ['ganada', 'calificada']],
            ['instagram', 12, $instagram, ['ganada', 'negociacion']],
            ['referido', 4, $referidos, ['ganada', 'propuesta_enviada']],
            ['llamada', 4, null, ['ganada']],
            ['linkedin', 3, null, ['negociacion']],
        ];
        $n = 0;

        foreach ($planes as [$codigo, $total, $campania, $etapas]) {
            foreach ($etapas as $etapa) {
                $op = $this->tomar($libres, $etapa);

                if ($op === null) {
                    continue;
                }

                $org = (string) DB::table('cuentas as c')->join('parties as p', 'p.id', '=', 'c.party_id')->where('c.id', $op->cuenta_id)->value('p.razon_social');
                $this->lead($canal[$codigo], $campania, $n++, $inicio, $hoy, ['organizacion' => $org, 'cuenta_id' => $op->cuenta_id, 'oportunidad_id' => $op->id, 'estado' => 'convertido', 'mensaje' => "Interesado en: {$op->nombre}"]);
                $total--;
            }

            for ($i = 0; $i < $total; $i++) {
                // Casi todos ya se atendieron y descartaron; uno o dos siguen por calificar.
                $estado = $i === 0 ? 'nuevo' : ($i === 1 && $total > 6 ? 'contactado' : 'descartado');
                $this->lead($canal[$codigo], $campania, $n++, $inicio, $hoy, ['estado' => $estado,
                    'motivo_descarte' => $estado === 'descartado' ? ['Solo pedía información', 'No es mercado para Techquk', 'No respondió', 'Sin presupuesto'][$i % 4] : null]);
            }
        }

        // Ayer, desde la página web: el aviso «Nuevo contacto desde la página web» en Mien.
        $web = $this->lead($canal['sitio_web'], null, $n, $inicio, $hoy, ['estado' => 'nuevo', 'nombre_contacto' => 'Gabriela Montes Ríos', 'organizacion' => 'Clínica Santa Lucía del Norte S.A.C.',
            'email' => 'gmontes@ejemplo.pe', 'mensaje' => 'Queremos renovar el cableado y las cámaras de la clínica. ¿Pueden visitarnos?']);
        DB::table('leads')->where('id', $web->id)->update(['created_at' => $hoy->subDay()->setTime(16, 40)]);
    }

    /** Observación 9.3: las campañas y leads sembrados antes del canal lo toman de su tipo, su campaña o su origen. */
    private function completarCanales(Collection $canal): void
    {
        foreach (['web' => 'sitio_web', 'feria' => 'feria', 'referidos' => 'referido', 'redes' => 'facebook', 'correo' => 'correo', 'clientes' => 'correo'] as $tipo => $codigo) {
            DB::table('campanias')->whereNull('canal_lead_id')->where('tipo', $tipo)->update(['canal_lead_id' => $canal[$codigo]]);
        }

        DB::statement('UPDATE leads SET canal_lead_id = c.canal_lead_id FROM campanias c WHERE c.id = leads.campania_id AND leads.canal_lead_id IS NULL');
        $origen = DB::table('origenes_comerciales')->pluck('id', 'codigo');

        foreach (['SITIO_WEB' => 'sitio_web', 'FERIA' => 'feria', 'REFERENCIA' => 'referido'] as $codigoOrigen => $codigo) {
            DB::table('leads')->whereNull('canal_lead_id')->where('origen_comercial_id', $origen[$codigoOrigen] ?? 0)->update(['canal_lead_id' => $canal[$codigo]]);
        }

        DB::table('leads')->whereNull('canal_lead_id')->update(['canal_lead_id' => $canal['otro']]);
    }

    /** @param  Collection<string, Collection<int, object>>  $libres */
    private function tomar(Collection $libres, string $etapa): ?object
    {
        $grupo = $libres->get($etapa);

        return $grupo === null || $grupo->isEmpty() ? null : $grupo->shift();
    }

    /** @param  array<string, mixed>  $datos */
    private function lead(int $canalId, ?Campania $campania, int $i, CarbonImmutable $inicio, CarbonImmutable $hoy, array $datos): Lead
    {
        $nombres = ['Andrea Valdivia', 'Bruno Cárdenas', 'Cecilia Huamán', 'Daniel Ponce', 'Estela Mori', 'Fabián Rengifo', 'Gloria Tello', 'Iván Saavedra', 'Julia Ccori', 'Kevin Lazo',
            'Lorena Pinedo', 'Mario Alva', 'Nadia Quiroz', 'Omar Seminario', 'Patricia Uceda', 'Raúl Inga', 'Sonia Vela', 'Tomás Yupanqui', 'Úrsula Neyra', 'Walter Zegarra'];
        $orgs = ['Bodega La Económica', 'Minimarket Don Lucho', 'Academia Pre Futuro', 'Colegio Virgen del Carmen', 'Restaurante El Algarrobo', 'Hostal Las Dunas', 'Ferretería San José', 'Grifo El Norteño', null, null];
        $origen = match (DB::table('canales_lead')->where('id', $canalId)->value('codigo')) {
            'feria' => 'FERIA', 'referido' => 'REFERENCIA', 'llamada' => 'VISITA_FRIO', default => 'SITIO_WEB',
        };
        $dias = max(1, $hoy->day - 1);
        $l = Lead::query()->create([
            'campania_id' => $campania?->id, 'canal_lead_id' => $canalId, 'origen_comercial_id' => (int) DB::table('origenes_comerciales')->where('codigo', $origen)->value('id'),
            'nombre_contacto' => $nombres[$i % 20], 'organizacion' => $orgs[$i % 10], 'telefono' => '9'.str_pad((string) (61000000 + $i * 977), 8, '0', STR_PAD_LEFT),
            'mensaje' => ['¿Cuánto cuestan 4 cámaras?', 'Quiero wifi para mi local.', 'Necesito una cotización de cableado.', 'Info de alarmas, por favor.'][$i % 4],
            ...$datos,
        ]);
        DB::table('leads')->where('id', $l->id)->update(['created_at' => $inicio->addDays($i % $dias)->setTime(9 + $i % 8, ($i * 7) % 60)]);

        return $l;
    }
}
