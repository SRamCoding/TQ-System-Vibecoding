<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Crm\Oportunidades\Enums\GanadorConocido;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Services\DatosOportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Observación 9.5 (ficha r1-oportunidades-vistas, MV-099): que se vea cada caso de las diez mejoras de Oportunidades.
 * Idempotente: cada parte revisa si ya está.
 */
final class MejorasOportunidadesSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->propuestaSinRespuesta();
            $this->esperandoAprobacion();
            $this->fichaCompleta();
            $this->metas();
        });
        // Las perdidas van fuera de la transacción de arriba: cada una se crea y se pierde en su fecha.
        DB::transaction(function (): void {
            $this->perdidas();
        });
    }

    /** (6) «CCTV perimetral de la planta concentradora» lleva 9 días en Propuesta enviada sin ninguna actividad. */
    private function propuestaSinRespuesta(): void
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $o = DB::table('oportunidades')->where('nombre', 'CCTV perimetral de la planta concentradora')->first(['id', 'en_etapa_desde']);

        if ($o === null) {
            return;
        }

        $desde = $hoy->subDays(9)->setTime(10, 0);
        DB::table('oportunidades')->where('id', $o->id)->update(['en_etapa_desde' => $desde]);
        DB::table('actividades')->where('oportunidad_id', $o->id)->where('created_at', '>=', $desde)->update(['created_at' => $desde->subDay()]);
    }

    /**
     * (2) La cotización bajo el mínimo que espera aprobación, pedida por el camino de verdad (abre su ronda) con su motivo:
     * sale en Mien → «Por aprobar».
     */
    private function esperandoAprobacion(): void
    {
        $c = Cotizacion::query()->whereHas('estado', fn ($q) => $q->where('codigo', 'por_aprobar'))->first();

        if ($c === null || DB::table('rondas_aprobacion')->where('aprobable_type', 'cotizacion')->where('aprobable_id', $c->id)->where('estado', 'abierta')->exists()) {
            return;
        }

        $usuario = User::query()->where('email', 'dev@techquk.test')->first() ?? User::query()->where('es_sistema', false)->firstOrFail();
        auth()->setUser($usuario);
        $borrador = (int) DB::table('estados')->where('documento_type', 'cotizacion')->where('codigo', 'borrador')->value('id');
        DB::table('cotizaciones')->where('id', $c->id)->update(['estado_id' => $borrador]);
        $c->refresh();
        app(CotizacionesService::class)->solicitarAprobacion($c->id, $c->version,
            'Competencia fuerte: Redes Andinas ofertó 10 % menos en el proceso anterior de esta municipalidad.', $usuario);
    }

    /** (8, 9 y 10) La ficha de «CCTV perimetral de la planta concentradora»: quién decide, competidores y resumen sugerido. */
    private function fichaCompleta(): void
    {
        $o = DB::table('oportunidades')->where('nombre', 'CCTV perimetral de la planta concentradora')->first(['id', 'cuenta_id']);

        if ($o === null || DB::table('oportunidad_decisores')->where('oportunidad_id', $o->id)->exists()) {
            return;
        }

        $autor = (int) (DB::table('users')->where('email', 'dev@techquk.test')->value('id') ?? 1);

        foreach ([
            ['Carlos Vega Arrieta', 'Gerente de operaciones', 'decide', 'a_favor', null],
            ['Lucía Torres Saldaña', 'Jefa de seguridad', 'influye', 'a_favor', null],
            ['Marco Díaz Peralta', 'Logística', 'usa', 'neutral', null],
            ['Paula Rey Gamarra', 'Compras', 'bloquea', 'en_contra', 'prefiere al proveedor actual'],
        ] as [$nombre, $cargo, $papel, $postura, $nota]) {
            DB::table('oportunidad_decisores')->insert(['oportunidad_id' => $o->id, 'nombre' => $nombre, 'cargo' => $cargo, 'papel' => $papel, 'postura' => $postura, 'nota' => $nota,
                'created_by' => $autor, 'created_at' => now(), 'updated_at' => now()]);
        }

        $pen = (int) DB::table('monedas')->where('codigo', 'PEN')->value('id');

        foreach ([['Conectividad del Pacífico S.A.C.', '478000.00', 'Les ganó 2 de 3 procesos en esta minera'], ['Seguridad Norte S.A.C.', '495000.00', 'Proveedor actual del CCTV']] as [$razon, $precio, $nota]) {
            $party = DB::table('parties')->where('razon_social', $razon)->value('id');

            if ($party !== null && ! DB::table('oportunidad_competidores')->where('oportunidad_id', $o->id)->where('party_id', $party)->exists()) {
                DB::table('oportunidad_competidores')->insert(['oportunidad_id' => $o->id, 'party_id' => $party, 'precio' => $precio, 'moneda_id' => $pen, 'precio_desconocido' => false,
                    'es_ganador' => false, 'fortalezas' => $nota, 'version' => 1, 'created_by' => $autor, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        // (10) Ya sugerido: en la demo no se llama a la IA (AGENTS.md, regla 7).
        DB::table('oportunidades')->where('id', $o->id)->update([
            'resumen_ia' => 'Propuesta enviada hace 9 días por S/ 512,000 (margen previsto 30.2 %). El gerente de operaciones pidió bajar el plazo de 60 a 45 días y nadie le ha respondido todavía; Compras prefiere al proveedor actual, que cotizaría cerca de S/ 495,000.',
            'siguiente_paso_ia' => 'Llamar a Carlos Vega (decide) con una propuesta de plazo de 50 días antes del viernes.',
            'resumen_ia_at' => now()->subHours(3),
        ]);
    }

    /** (5) Metas del trimestre para tres ejecutivos, con lo ganado repartido: una cumplida, una en ámbar y una en coral. */
    private function metas(): void
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());

        if (DB::table('metas_comerciales')->where('anio', $hoy->year)->exists()) {
            return;
        }

        $autor = (int) (DB::table('users')->where('email', 'dev@techquk.test')->value('id') ?? 1);
        $quien = fn (string $correo): ?int => DB::table('users')->where('email', $correo)->value('id');
        $ganadas = DB::table('oportunidades as o')->join('estados as e', 'e.id', '=', 'o.estado_id')->where('e.codigo', 'ganada')
            ->whereBetween('o.fecha_cierre_real', [$hoy->startOfQuarter()->toDateString(), $hoy->toDateString()])->orderByDesc('o.monto_estimado')->get(['o.id', 'o.monto_estimado']);
        $plan = [['diego.salazar@techquk.test', 0.84], ['andrea.vilchez@techquk.test', 1.85], ['rosa.quispe@techquk.test', null]];

        foreach ($plan as $i => [$correo, $factor]) {
            $u = $quien($correo);

            if ($u === null) {
                continue;
            }

            $g = $ganadas[$i] ?? null;

            if ($g !== null && $factor !== null) {
                DB::table('oportunidades')->where('id', $g->id)->update(['ejecutivo_user_id' => $u]);
            }

            // La meta: lo ganado × el factor (redondeado a miles) o, sin ganadas, S/ 120,000.
            $meta = $g !== null && $factor !== null ? (string) (round((float) $g->monto_estimado * $factor / 1000) * 1000) : '120000';

            for ($t = 1; $t <= 4; $t++) {
                DB::table('metas_comerciales')->insert(['user_id' => $u, 'anio' => $hoy->year, 'trimestre' => $t, 'monto' => $t === $hoy->quarter ? $meta : '150000.00',
                    'created_by' => $autor, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    /** (3) Perdidas del año con motivos y ganadores variados, por el camino de verdad (crear y perder en su fecha). */
    private function perdidas(): void
    {
        if (DB::table('oportunidades')->where('nombre', 'Wifi para huéspedes y áreas comunes')->exists()) {
            return;
        }

        $dev = User::query()->where('email', 'dev@techquk.test')->first();

        if ($dev === null) {
            return;
        }

        auth()->setUser($dev);
        $servicio = app(OportunidadesService::class);
        $pen = (int) DB::table('monedas')->where('codigo', 'PEN')->value('id');
        $linea = fn (string $c): int => (int) DB::table('lineas_solucion')->where('codigo', $c)->value('id');
        $cuenta = fn (string $razon): ?int => DB::table('cuentas as c')->join('parties as p', 'p.id', '=', 'c.party_id')->where('p.razon_social', $razon)->value('c.id');
        $party = fn (string $razon): ?int => DB::table('parties')->where('razon_social', $razon)->value('id');
        $motivo = fn (string $codigo): int => (int) DB::table('motivos as m')->join('tipos_motivo as t', 't.id', '=', 'm.tipo_motivo_id')->where('t.codigo', 'crm.oportunidades.oportunidad.perder')->where('m.codigo', $codigo)->value('m.id');
        $hoy = CarbonImmutable::parse(Calendario::hoy());

        // [nombre, cuenta, línea, monto, perdida hace N días, motivo, ganador, precio del ganador]
        $casos = [
            ['Wifi para huéspedes y áreas comunes', 'Hotel Mirador del Valle S.A.C.', 'REDES', '64000.00', 12, 'precio', 'Conectividad del Pacífico S.A.C.', '57200.00'],
            ['Cámaras para la flota de buses', 'Transportes Ruta Andina S.A.C.', 'VIDEOVIGILANCIA', '138000.00', 20, 'precio', 'Seguridad Norte S.A.C.', '124500.00'],
            ['Control de acceso de las aulas', 'Colegio Los Andes del Norte S.A.C.', 'VIDEOVIGILANCIA', '42000.00', 27, 'plazo', 'Andes Tech E.I.R.L.', '43900.00'],
            ['Red de datos del almacén de frío', 'Agroexportadora Valle Fértil S.A.C.', 'REDES', '96000.00', 35, 'precio', 'Conectividad del Pacífico S.A.C.', '86400.00'],
            ['Software de tickets de atención', 'Supermercados La Canasta S.A.C.', 'SOFTWARE', '58000.00', 41, 'experiencia', 'Sistemas Integrados de Salud S.A.C.', null],
            ['Cámaras del campo de riego', 'Agrícola Pampa Verde S.A.C.', 'VIDEOVIGILANCIA', '77000.00', 48, 'precio', 'Seguridad Norte S.A.C.', '68900.00'],
            ['Servidores del área de proyectos', 'Proyecto Especial Hidráulico Valle Norte', 'DATACENTER', '210000.00', 52, 'presupuesto_cancelado', null, null],
            ['Videovigilancia del terminal terrestre', 'Municipalidad Distrital de La Pampa Alta', 'VIDEOVIGILANCIA', '185000.00', 130, 'precio', 'Seguridad Norte S.A.C.', '160200.00'],
            ['Enlaces inalámbricos entre locales', 'Universidad Nacional del Valle Norte', 'REDES', '120000.00', 160, 'especificaciones', 'Andes Tech E.I.R.L.', '118000.00'],
            ['Wifi de la plaza principal', 'Municipalidad Distrital de Los Laureles', 'REDES', '54000.00', 200, 'plazo', 'Conectividad del Pacífico S.A.C.', '50100.00'],
        ];

        foreach ($casos as [$nombre, $razon, $codigoLinea, $monto, $hace, $codigoMotivo, $ganador, $precio]) {
            $c = $cuenta($razon);

            if ($c === null) {
                continue;
            }

            $dia = $hoy->subDays($hace);
            $this->en($dia->subDays(40), function () use ($servicio, $c, $nombre, $monto, $pen, $dia, $linea, $codigoLinea, $dev, &$id): void {
                $id = $servicio->crear($c, new DatosOportunidad($nombre, null, $monto, $pen, $dia, $linea($codigoLinea), ejecutivoUserId: $dev->id), [], $dev)->id;
            });
            $this->en($dia, function () use ($servicio, $id, $motivo, $codigoMotivo, $ganador, $party, $precio, $pen, $dev): void {
                $o = Oportunidad::query()->findOrFail($id);
                $ganadorId = $ganador === null ? null : $party($ganador);
                $servicio->perder($o->id, $o->version, new MotivoElegido($motivo($codigoMotivo)),
                    $ganadorId === null ? GanadorConocido::NoSeSabe : GanadorConocido::Si,
                    $ganadorId, $ganadorId !== null ? $precio : null, $ganadorId !== null && $precio !== null ? $pen : null, $ganadorId !== null && $precio === null, null, $dev);
            });
        }

        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }

    private function en(CarbonImmutable $dia, callable $paso): void
    {
        $momento = CarbonImmutable::parse($dia->toDateString(), 'America/Lima')->setTime(10, 0);
        Carbon::setTestNow($momento);
        CarbonImmutable::setTestNow($momento);
        $paso();
    }
}
