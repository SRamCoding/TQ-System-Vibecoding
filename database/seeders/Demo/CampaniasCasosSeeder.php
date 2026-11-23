<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Modules\Crm\Campanias\Models\Campania;
use App\Modules\Crm\Campanias\Models\Lead;
use App\Modules\Crm\Campanias\Services\CampaniasService;
use App\Modules\Erp\Postventa\Services\PostventaService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Observación 7.6 (ficha t1-campanias, MV-079): las campañas del lienzo (o equivalentes) con sus contactos,
 * oportunidades, proyectos ganados y gastos reales calculados para que el retorno dé lo del lienzo: la feria 9.1× (y se
 * pasó del presupuesto), Facebook a colegios 6.7×, correo a municipalidades 0× y referidos 14.1×. Mis clientes: series con
 * la garantía por vencer, tres proyectos instalados hace más de 3 años y una campaña a clientes con sus tareas en Mien.
 * Idempotente: si ya está la campaña de Facebook, no hace nada.
 */
final class CampaniasCasosSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(fn () => $this->sembrar());
    }

    private function sembrar(): void
    {
        if (Campania::query()->where('nombre', 'Facebook · colegios privados')->exists() || ! Campania::query()->exists()) {
            return;
        }

        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $usuario = User::query()->where('email', 'dev@techquk.test')->first() ?? User::query()->where('es_sistema', false)->firstOrFail();
        auth()->setUser($usuario);
        $s = app(CampaniasService::class);

        $feria = Campania::query()->where('tipo', 'feria')->firstOrFail();
        $feria->update(['presupuesto' => '2400.00']);
        $referidos = Campania::query()->where('tipo', 'referidos')->firstOrFail();
        $facebook = Campania::query()->create(['nombre' => 'Facebook · colegios privados', 'tipo' => 'redes', 'desde' => '2026-08-01', 'hasta' => '2026-12-31', 'presupuesto' => '2500.00',
            'objetivo' => 'Anuncios para colegios privados de la región: cámaras y red.', 'es_activa' => true]);
        $correo = Campania::query()->create(['nombre' => 'Correo a municipalidades', 'tipo' => 'correo', 'desde' => '2026-10-01', 'presupuesto' => null,
            'objetivo' => 'Correo a las municipalidades distritales con casos de videovigilancia.', 'es_activa' => true]);

        // Contactos: los que convirtieron (con su oportunidad) y el resto, hasta llegar al total del lienzo.
        $this->contactos($feria, [11, 4], 15, $hoy);
        $this->contactos($facebook, [16, 17, 22, 2], 42, $hoy);
        $this->contactos($correo, [12, 14], 9, $hoy);
        $this->contactos($referidos, [5, 7, 23], 6, $hoy);

        // Gastos reales: el margen de lo ganado ÷ el retorno del lienzo.
        $sede = (int) DB::table('party_sedes')->where('party_id', app(EmpresaService::class)->ficha()?->id ?? 0)->orderBy('id')->value('id') ?: null;
        $gastos = [
            [$feria, 9.1, CarbonImmutable::parse('2026-09-20'), 'Stand y material de la feria', 'Eventos del Norte S.A.C.'],
            [$facebook, 6.7, CarbonImmutable::parse('2026-10-05'), 'Anuncios en Facebook e Instagram', 'Meta Platforms Ireland Ltd.'],
            [$referidos, 14.1, CarbonImmutable::parse('2026-07-15'), 'Bonos de referidos pagados', 'Clientes referidores'],
        ];

        foreach ($gastos as [$c, $retorno, $fecha, $desc, $prov]) {
            $margen = (float) $s->tarjeta($c->refresh())['margen'];
            $monto = number_format($margen / $retorno, 2, '.', '');
            $mitad = number_format((float) $monto / 2, 2, '.', '');
            $s->registrarGasto($c->id, $sede, $fecha, $mitad, "{$desc} (1/2)", $prov, 'Gasto de campaña comercial');
            $s->registrarGasto($c->id, $sede, $fecha->addDays(10), number_format((float) $monto - (float) $mitad, 2, '.', ''), "{$desc} (2/2)", $prov, 'Gasto de campaña comercial');
        }

        $s->registrarGasto($correo->id, $sede, CarbonImmutable::parse('2026-10-08'), '180.00', 'Envío masivo de correos', 'Mailing Perú S.A.C.', 'Gasto de campaña comercial');

        $this->misClientes($hoy);

        $valeria = User::query()->where('name', 'like', 'Valeria%')->first() ?? $usuario;
        $garantia = $s->grupos()['garantia']['clientes'];

        if ($garantia !== []) {
            $s->crearCampaniaAClientes('garantia', 'Mantenimiento preventivo 2026', array_values(array_unique(array_column($garantia, 'cuenta'))), CampaniasService::SEGUIMIENTO, $valeria);
        }
    }

    /** @param  list<int>  $oportunidades */
    private function contactos(Campania $c, array $oportunidades, int $total, CarbonImmutable $hoy): void
    {
        $origen = (int) DB::table('origenes_comerciales')->where('codigo', $c->tipo === 'feria' ? 'FERIA' : ($c->tipo === 'referidos' ? 'REFERENCIA' : 'SITIO_WEB'))->value('id');
        $ya = Lead::query()->where('campania_id', $c->id)->count();
        $nombres = ['Carla Ruiz', 'Jorge Paredes', 'Lucía Flores', 'Martín Chávez', 'Rosa Benites', 'Óscar Vílchez', 'Diana Salas', 'Pablo Ríos', 'Elena Castro', 'Hugo Zapata'];

        foreach ($oportunidades as $i => $op) {
            if (Lead::query()->where('oportunidad_id', $op)->exists()) {
                continue;
            }

            $o = DB::table('oportunidades')->find($op);
            $org = (string) DB::table('cuentas as c')->join('parties as p', 'p.id', '=', 'c.party_id')->where('c.id', $o->cuenta_id)->value('p.razon_social');
            Lead::query()->create(['campania_id' => $c->id, 'origen_comercial_id' => $origen, 'nombre_contacto' => $nombres[$i % 10], 'organizacion' => $org, 'cuenta_id' => $o->cuenta_id,
                'oportunidad_id' => $op, 'estado' => 'convertido', 'mensaje' => "Interesado en: {$o->nombre}"]);
            $ya++;
        }

        for ($i = $ya; $i < $total; $i++) {
            $estado = $i % 9 === 0 ? 'nuevo' : ($i % 3 === 0 ? 'descartado' : 'contactado');
            $l = Lead::query()->create(['campania_id' => $c->id, 'origen_comercial_id' => $origen, 'nombre_contacto' => $nombres[$i % 10], 'organizacion' => $c->tipo === 'redes' ? 'Colegio '.['San Martín', 'Los Pinos', 'Santa Ana', 'El Buen Pastor', 'Nuevo Horizonte', 'La Alborada'][$i % 6] : null,
                'telefono' => '9'.str_pad((string) (87000000 + $i * 731), 8, '0', STR_PAD_LEFT), 'estado' => $estado, 'motivo_descarte' => $estado === 'descartado' ? 'Solo pedía información' : null,
                'mensaje' => 'Quiero una cotización.']);
            DB::table('leads')->where('id', $l->id)->update(['created_at' => $hoy->subDays(5 + $i % 20)]);
        }
    }

    /** Series con la garantía por vencer y proyectos de hace más de 3 años (atajo: estos últimos se insertan directo). */
    private function misClientes(CarbonImmutable $hoy): void
    {
        $series = DB::table('series')->whereNull('proyecto_id')->orderBy('serie')->pluck('serie')->all();
        $destinos = [[2, 21], [3, 48], [4, 63], [7, 80]];

        foreach ($destinos as $i => [$proyecto, $dias]) {
            if (isset($series[$i]) && DB::table('proyectos')->where('id', $proyecto)->exists()) {
                app(PostventaService::class)->instalar($series[$i], $proyecto, $hoy->addDays($dias)->subMonths(24), 'Sede del cliente');
            }
        }

        $cerrado = (int) DB::table('estados')->where('documento_type', 'proyecto')->where('codigo', 'cerrado')->value('id');
        $base = DB::table('proyectos')->orderBy('id')->first();

        if ($cerrado === 0 || $base === null) {
            return;
        }

        $antiguos = [
            ['PRY-2021-0004', 'CCTV del mercado central', 9, '2021-06-01', '2021-11-30'],
            ['PRY-2022-0002', 'Cámaras y control de acceso de almacenes', 13, '2022-03-01', '2022-08-15'],
            ['PRY-2023-0001', 'Videovigilancia de la planta de empaque', 10, '2023-01-09', '2023-05-31'],
        ];

        // Cada proyecto necesita su oportunidad: la de entonces, ganada, copiada de una ganada de la demo.
        $modelo = (array) DB::table('oportunidades as o')->join('estados as e', 'e.id', '=', 'o.estado_id')->where('e.codigo', 'ganada')->orderBy('o.id')->select('o.*')->first();
        $linea = DB::table('lineas_solucion')->where('nombre', 'Videovigilancia')->value('id');

        foreach ($antiguos as $i => [$codigo, $nombre, $party, $inicio, $fin]) {
            $cuenta = DB::table('cuentas')->where('party_id', $party)->value('id');

            if ($modelo === [] || $cuenta === null || DB::table('proyectos')->where('codigo', $codigo)->exists()) {
                continue;
            }

            $op = DB::table('oportunidades')->insertGetId([...array_diff_key($modelo, ['id' => 0]), 'numero' => 'OP-'.substr($inicio, 0, 4).'-09'.($i + 1), 'cuenta_id' => $cuenta, 'nombre' => $nombre,
                'monto_estimado' => ['86000.00', '142000.00', '118500.00'][$i], 'fecha_cierre_estimada' => $inicio, 'fecha_cierre_real' => $inicio, 'en_etapa_desde' => $inicio,
                'linea_solucion_id' => $linea ?? $modelo['linea_solucion_id'], 'foto_ruta' => null, 'created_at' => $inicio, 'updated_at' => $inicio]);
            DB::table('proyectos')->insert(['codigo' => $codigo, 'nombre' => $nombre, 'party_id' => $party, 'es_interno' => false, 'oportunidad_id' => $op, 'moneda_id' => $base->moneda_id,
                'jefe_proyecto_user_id' => $base->jefe_proyecto_user_id, 'fecha_inicio' => $inicio, 'fecha_fin_prevista' => $fin, 'estado_id' => $cerrado, 'version' => 1,
                'created_by' => $base->created_by, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
