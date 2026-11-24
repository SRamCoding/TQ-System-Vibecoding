<?php

declare(strict_types=1);

namespace App\Modules\Crm\Campanias\Services;

use App\Modules\Crm\Actividades\Services\ActividadesService;
use App\Modules\Crm\Campanias\Models\Campania;
use App\Modules\Crm\Campanias\Models\Lead;
use App\Modules\Erp\Presupuestos\Services\DineroDelProyecto;
use App\Modules\Erp\Presupuestos\Services\PresupuestoEmpresaService;
use App\Modules\Erp\Proyectos\Services\VentaDelProyecto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * MVP rápido (observación 7.6, MV-079, ficha t1-campanias): las campañas con su retorno y las campañas a los propios
 * clientes. Retorno = margen de lo ganado ÷ lo gastado. Lo gastado es la suma de gastos reales (gastos directos del
 * centro de costo Comercial, rubro «Publicidad y ferias», vinculados a la campaña). El margen de lo ganado sale del
 * proyecto de cada oportunidad ganada: el real del cierre si ya cerró; si no, el previsto (marcado así).
 */
final readonly class CampaniasService
{
    public const string RUBRO = 'Publicidad y ferias';

    public const string CENTRO_COSTO = 'COM';

    /** Mis clientes: la garantía vence en estos días o menos. */
    public const int DIAS_GARANTIA = 90;

    /** Mis clientes: instalado hace estos años o más. */
    public const int ANIOS_RENOVACION = 3;

    /** Estimados de Mis clientes (supuestos): mantenimiento = 10 % de lo vendido; renovación = 60 %; otra línea, fijo. */
    public const array ESTIMADOS = ['mantenimiento' => '0.10', 'renovacion' => '0.60', 'otra_linea' => '40000.00', 'sin_venta' => '150000.00'];

    /** El seguimiento por defecto de una campaña a clientes (editable). */
    public const array SEGUIMIENTO = [
        ['dia' => 1, 'canal' => 'correo', 'texto' => 'Hola, {cliente}. En Techquk queremos ofrecerle {oferta} para {proyecto}. ¿Le enviamos la propuesta?'],
        ['dia' => 3, 'canal' => 'whatsapp', 'texto' => 'Hola, {cliente}: le escribimos por la propuesta de {oferta} que le enviamos por correo. ¿Conversamos esta semana?'],
        ['dia' => 7, 'canal' => 'llamada', 'texto' => 'Llamar a {cliente} para cerrar la propuesta de {oferta}.'],
    ];

    public const array GRUPOS = [
        'garantia' => ['Garantía por vencer en 90 días', 'Ofrecer mantenimiento preventivo', 'mantenimiento preventivo'],
        'renovacion' => ['Instalado hace 3 años o más', 'Ofrecer renovación de equipos', 'la renovación de sus equipos'],
        'una_linea' => ['Solo tienen una línea', 'Ofrecer otra línea (red, software o videovigilancia)', 'una solución de otra línea'],
    ];

    /**
     * Las cinco cifras del año y una tarjeta por campaña (las activas o con movimiento en el año).
     *
     * @return array{cifras: array<string, mixed>, tarjetas: list<array<string, mixed>>}
     */
    public function retorno(int $anio): array
    {
        $tarjetas = Campania::query()->orderByDesc('desde')->get()->map(fn (Campania $c): array => $this->tarjeta($c))->values()->all();
        $suma = fn (string $k): string => array_reduce($tarjetas, fn (string $t, array $x): string => bcadd($t, (string) $x[$k], 2), '0.00');
        $contactos = array_sum(array_column($tarjetas, 'contactos'));
        $oportunidades = array_sum(array_column($tarjetas, 'oportunidades'));
        $gastado = $suma('gastado');
        $margen = $suma('margen');

        return ['cifras' => [
            'gastado' => $gastado, 'presupuesto' => $suma('presupuesto'), 'contactos' => $contactos,
            'porContacto' => $contactos === 0 ? null : bcdiv($gastado, (string) $contactos, 2),
            'oportunidades' => $oportunidades, 'tasa' => $contactos === 0 ? '0' : number_format($oportunidades * 100 / $contactos, 0),
            'ganado' => $suma('ganado'), 'proyectos' => array_sum(array_column($tarjetas, 'ganadas')), 'margen' => $margen,
            'retorno' => bccomp($gastado, '0', 2) > 0 ? number_format((float) $margen / (float) $gastado, 1, '.', '') : null, 'anio' => $anio,
        ], 'tarjetas' => $tarjetas];
    }

    /** @return array<string, mixed> */
    public function tarjeta(Campania $c): array
    {
        $leads = Lead::query()->where('campania_id', $c->id)->get(['id', 'oportunidad_id', 'estado', 'created_at']);
        $ops = $leads->pluck('oportunidad_id')->filter()->values()->all();
        $ganadas = DB::table('oportunidades as o')->join('estados as e', 'e.id', '=', 'o.estado_id')->whereIn('o.id', $ops === [] ? [0] : $ops)->where('e.codigo', 'ganada')->get(['o.id', 'o.monto_estimado']);
        $margen = '0.00';
        $previsto = false;

        foreach ($ganadas as $g) {
            $m = $this->margenDeOportunidad((int) $g->id);
            $margen = bcadd($margen, $m['monto'] ?? '0', 2);
            $previsto = $previsto || $m['previsto'];
        }

        $gastado = (string) (DB::table('campania_gastos as cg')->join('gastos_demo as g', 'g.id', '=', 'cg.gasto_id')->where('cg.campania_id', $c->id)->sum('g.monto') ?: '0');
        $gastado = bcadd($gastado, '0', 2);
        $presupuesto = $c->presupuesto === null ? '0.00' : (string) $c->presupuesto;
        $sinAtender = $leads->where('estado', 'nuevo')->filter(fn ($l): bool => $l->created_at->lt(Calendario::ahora()->subDays(LeadsService::DIAS_SIN_ATENDER)))->count();

        return [
            'id' => $c->id, 'nombre' => $c->nombre, 'tipo' => Campania::TIPOS[$c->tipo] ?? $c->tipo, 'claveTipo' => $c->tipo,
            'contactos' => $leads->count(), 'oportunidades' => count($ops), 'ganadas' => $ganadas->count(),
            'ganado' => bcadd((string) ($ganadas->sum('monto_estimado') ?: '0'), '0', 2), 'margen' => $margen, 'margenPrevisto' => $previsto,
            'gastado' => $gastado, 'presupuesto' => $presupuesto, 'sePaso' => bccomp($gastado, $presupuesto, 2) > 0,
            'retorno' => bccomp($gastado, '0', 2) > 0 ? number_format((float) $margen / (float) $gastado, 1, '.', '') : null, 'sinAtender' => $sinAtender,
            'destinatarios' => (int) DB::table('campania_destinatarios')->where('campania_id', $c->id)->count(),
        ];
    }

    /**
     * El margen de una oportunidad ganada, con el mismo cálculo del resto del sistema: el real del cierre del proyecto
     * si ya cerró; si no, el previsto del proyecto (venta − presupuestado).
     *
     * @return array{monto: ?string, previsto: bool}
     */
    public function margenDeOportunidad(int $oportunidadId): array
    {
        $proyecto = DB::table('proyectos')->where('oportunidad_id', $oportunidadId)->value('id');

        if ($proyecto === null) {
            return ['monto' => null, 'previsto' => true];
        }

        $real = DB::table('proyecto_cierres')->where('proyecto_id', $proyecto)->value('margen_real');

        if ($real !== null) {
            return ['monto' => (string) $real, 'previsto' => false];
        }

        return ['monto' => app(DineroDelProyecto::class)->de((int) $proyecto)['margen_previsto'] ?? null, 'previsto' => true];
    }

    /** Un gasto real: gasto directo del centro de costo Comercial, vinculado a la campaña. */
    public function registrarGasto(int $campaniaId, ?int $sedeId, CarbonImmutable $fecha, string $monto, string $descripcion, string $proveedor, ?string $motivo): void
    {
        DB::transaction(function () use ($campaniaId, $sedeId, $fecha, $monto, $descripcion, $proveedor, $motivo): void {
            $centro = (int) DB::table('centros_costo')->where('codigo', self::CENTRO_COSTO)->value('id');
            $gasto = app(PresupuestoEmpresaService::class)->gastoDirecto($sedeId, self::RUBRO, $centro, $fecha, $monto, $monto, $descripcion, $proveedor, $motivo);
            DB::table('campania_gastos')->insert(['campania_id' => $campaniaId, 'gasto_id' => $gasto->id, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    /** El enlace público del formulario (se crea la primera vez). */
    public function token(int $campaniaId): string
    {
        $c = Campania::query()->findOrFail($campaniaId);

        if ($c->token === null) {
            $c->update(['token' => Str::lower(Str::random(24))]);
        }

        return (string) $c->token;
    }

    /** Lo que alguien envía desde el formulario público: entra a Oportunidades como «Por calificar», con la campaña. */
    public function recibirFormulario(string $token, string $nombre, ?string $organizacion, ?string $telefono, ?string $correo, ?string $necesita): void
    {
        $c = Campania::query()->where('token', $token)->where('es_activa', true)->firstOrFail();
        $origen = match ($c->tipo) {
            'feria' => 'FERIA', 'referidos', 'clientes' => 'REFERENCIA', default => 'SITIO_WEB',
        };

        Lead::query()->create([
            'campania_id' => $c->id, 'origen_comercial_id' => (int) DB::table('origenes_comerciales')->where('codigo', $origen)->value('id'),
            // Observación 9.3: el canal de la campaña (sin canal, Página web).
            'canal_lead_id' => $c->canal_lead_id ?? DB::table('canales_lead')->where('codigo', 'sitio_web')->value('id'),
            'nombre_contacto' => mb_substr($nombre, 0, 150), 'organizacion' => $organizacion === null ? null : mb_substr($organizacion, 0, 200),
            'telefono' => $telefono === null ? null : mb_substr($telefono, 0, 30), 'email' => $correo === null ? null : mb_substr($correo, 0, 150),
            'mensaje' => $necesita, 'estado' => 'nuevo',
        ]);
    }

    /**
     * Los avisos para Mien: «N contactos de [campaña] llevan 3 días sin atender».
     *
     * @return list<array{texto: string, nota: ?string, url: string, tono: ?string}>
     */
    public function avisos(): array
    {
        $items = [];

        foreach (Campania::query()->get() as $c) {
            $n = Lead::query()->where('campania_id', $c->id)->where('estado', 'nuevo')->where('created_at', '<', Calendario::ahora()->subDays(LeadsService::DIAS_SIN_ATENDER))->count();

            if ($n > 0) {
                $items[] = ['texto' => ($n === 1 ? '1 contacto' : "{$n} contactos")." de {$c->nombre} ".($n === 1 ? 'lleva' : 'llevan').' 3 días sin atender', 'nota' => null, 'url' => '/oportunidades?etapa[0]=por_calificar', 'tono' => 'danger'];
            }
        }

        return $items;
    }

    /**
     * Mis clientes: los tres grupos que el sistema arma solo, con su cantidad y un monto estimado.
     *
     * @return array<string, array{titulo: string, oferta: string, clientes: list<array<string, mixed>>, estimado: string}>
     */
    public function grupos(): array
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $venta = fn (int $p): ?string => app(VentaDelProyecto::class)->venta($p);
        $proyectos = DB::table('proyectos as p')->join('estados as e', 'e.id', '=', 'p.estado_id')->where('p.es_interno', false)
            ->get(['p.id', 'p.codigo', 'p.nombre', 'p.party_id', 'p.fecha_fin_prevista', 'p.oportunidad_id', 'e.codigo as estado']);
        $cuentaDe = DB::table('cuentas')->pluck('id', 'party_id');
        $nombreDe = DB::table('parties')->pluck('razon_social', 'id');
        $grupos = [];

        // Garantía por vencer: series instaladas con la garantía en los próximos 90 días.
        $garantias = DB::table('series')->whereNotNull('proyecto_id')->whereBetween('garantia_hasta', [$hoy->toDateString(), $hoy->addDays(self::DIAS_GARANTIA)->toDateString()])
            ->selectRaw('proyecto_id, min(garantia_hasta) as vence')->groupBy('proyecto_id')->pluck('vence', 'proyecto_id');
        $grupos['garantia'] = $proyectos->filter(fn ($p): bool => isset($garantias[$p->id]))->map(fn ($p): array => [
            'cuenta' => $cuentaDe[$p->party_id] ?? null, 'cliente' => $nombreDe[$p->party_id] ?? '—', 'proyecto' => "{$p->nombre} · {$p->codigo}", 'proyectoId' => $p->id,
            'dato' => 'vence en '.(int) $hoy->diffInDays(CarbonImmutable::parse($garantias[$p->id])).' días',
            'estimado' => ($v = $venta($p->id)) === null ? '8400.00' : bcmul($v, self::ESTIMADOS['mantenimiento'], 2),
        ])->sortBy('dato', SORT_NATURAL)->values()->all();

        // Instalado hace 3 años o más: proyectos cerrados que terminaron hace 3 años o más.
        $grupos['renovacion'] = $proyectos->filter(fn ($p): bool => $p->estado === 'cerrado' && $p->fecha_fin_prevista !== null && CarbonImmutable::parse($p->fecha_fin_prevista)->lte($hoy->subYears(self::ANIOS_RENOVACION)))
            ->map(fn ($p): array => [
                'cuenta' => $cuentaDe[$p->party_id] ?? null, 'cliente' => $nombreDe[$p->party_id] ?? '—', 'proyecto' => "{$p->nombre} · {$p->codigo}", 'proyectoId' => $p->id,
                'dato' => 'instalado en '.substr((string) $p->fecha_fin_prevista, 0, 4),
                'estimado' => ($v = $venta($p->id)) === null ? self::ESTIMADOS['sin_venta'] : bcmul($v, self::ESTIMADOS['renovacion'], 2),
            ])->values()->all();

        // Solo una línea: clientes cuyas oportunidades ganadas son todas de la misma línea de solución.
        $lineas = DB::table('oportunidades as o')->join('estados as e', 'e.id', '=', 'o.estado_id')->join('lineas_solucion as l', 'l.id', '=', 'o.linea_solucion_id')
            ->where('e.codigo', 'ganada')->get(['o.cuenta_id', 'l.nombre'])->groupBy('cuenta_id');
        $partyDe = DB::table('cuentas')->pluck('party_id', 'id');
        $grupos['una_linea'] = $lineas->filter(fn ($ls): bool => $ls->pluck('nombre')->unique()->count() === 1)->map(fn ($ls, $cuenta): array => [
            'cuenta' => (int) $cuenta, 'cliente' => $nombreDe[$partyDe[$cuenta] ?? 0] ?? '—', 'proyecto' => $ls->count().($ls->count() === 1 ? ' proyecto' : ' proyectos'), 'proyectoId' => null,
            'dato' => 'solo '.mb_strtolower((string) $ls->first()->nombre), 'estimado' => self::ESTIMADOS['otra_linea'],
        ])->values()->all();

        $resultado = [];

        foreach (self::GRUPOS as $clave => [$titulo, $oferta]) {
            $clientes = array_values(array_filter($grupos[$clave], fn (array $c): bool => $c['cuenta'] !== null));
            $resultado[$clave] = ['titulo' => $titulo, 'oferta' => $oferta, 'clientes' => $clientes,
                'estimado' => array_reduce($clientes, fn (string $t, array $c): string => bcadd($t, (string) $c['estimado'], 2), '0.00')];
        }

        return $resultado;
    }

    /**
     * «Crear campaña» desde un grupo: nace una campaña de tipo «Clientes» con esos destinatarios y su seguimiento; cada paso
     * se vuelve una tarea en la agenda (y en Mien) del responsable con el texto de la plantilla. No se envía nada.
     *
     * @param  list<int>  $cuentas
     * @param  list<array{dia: int|string, canal: string, texto: string}>  $pasos
     */
    public function crearCampaniaAClientes(string $grupo, string $nombre, array $cuentas, array $pasos, User $responsable): Campania
    {
        if (! isset(self::GRUPOS[$grupo]) || $cuentas === []) {
            throw new RuntimeException('Elige al menos un cliente del grupo.');
        }

        $datos = collect($this->grupos()[$grupo]['clientes'])->keyBy('cuenta');

        return DB::transaction(function () use ($grupo, $nombre, $cuentas, $pasos, $responsable, $datos): Campania {
            $hoy = CarbonImmutable::parse(Calendario::hoy(), 'America/Lima');
            $c = Campania::query()->create(['nombre' => mb_substr($nombre, 0, 150), 'tipo' => 'clientes', 'desde' => $hoy->toDateString(), 'presupuesto' => null,
                'objetivo' => self::GRUPOS[$grupo][1], 'es_activa' => true, 'grupo' => $grupo, 'seguimiento' => array_values($pasos), 'responsable_user_id' => $responsable->id]);
            $actividades = app(ActividadesService::class);

            foreach ($cuentas as $cuenta) {
                $d = $datos[$cuenta] ?? null;

                if ($d === null) {
                    continue;
                }

                DB::table('campania_destinatarios')->insert(['campania_id' => $c->id, 'cuenta_id' => $cuenta, 'proyecto_id' => $d['proyectoId'], 'dato' => $d['dato'], 'created_at' => now(), 'updated_at' => now()]);

                foreach ($pasos as $paso) {
                    $canal = ['correo' => 'Correo', 'whatsapp' => 'WhatsApp', 'llamada' => 'Llamada'][$paso['canal']] ?? $paso['canal'];
                    $actividades->registrar($paso['canal'] === 'correo' ? 'correo' : 'llamada', (int) $cuenta, null,
                        mb_substr("{$canal} · {$c->nombre} · {$d['cliente']}", 0, 200),
                        $hoy->addDays(max(0, (int) $paso['dia'] - 1))->setTime(9, 0), null, null, null, $responsable);
                }
            }

            return $c;
        });
    }

    /**
     * La ficha de la campaña: su tarjeta de retorno, los gastos reales y, si es a clientes, sus destinatarios y cada paso
     * del seguimiento con la plantilla ya armada para cada cliente.
     *
     * @return array<string, mixed>
     */
    public function ficha(Campania $c): array
    {
        $oferta = self::GRUPOS[$c->grupo ?? '']['2'] ?? 'nuestros servicios';
        $destinatarios = DB::table('campania_destinatarios as d')->join('cuentas as cu', 'cu.id', '=', 'd.cuenta_id')->join('parties as p', 'p.id', '=', 'cu.party_id')
            ->leftJoin('proyectos as pr', 'pr.id', '=', 'd.proyecto_id')->where('d.campania_id', $c->id)->orderBy('p.razon_social')
            ->get(['p.razon_social as cliente', 'pr.nombre as proyecto', 'd.dato'])->map(fn (object $d): array => (array) $d)->all();
        $pasos = array_map(fn (array $paso): array => [...$paso, 'mensajes' => array_map(fn (array $d): array => ['cliente' => $d['cliente'],
            'texto' => self::plantilla((string) $paso['texto'], $d['cliente'], $oferta, (string) ($d['proyecto'] ?? 'sus instalaciones'))], $destinatarios)], (array) ($c->seguimiento ?? []));

        return [
            'tarjeta' => $this->tarjeta($c), 'destinatarios' => $destinatarios, 'pasos' => $pasos,
            'gastos' => DB::table('campania_gastos as cg')->join('gastos_demo as g', 'g.id', '=', 'cg.gasto_id')->where('cg.campania_id', $c->id)->orderBy('g.fecha')
                ->get(['g.numero', 'g.fecha', 'g.descripcion', 'g.proveedor', 'g.monto'])->map(fn (object $g): array => (array) $g)->all(),
        ];
    }

    /** La plantilla con los datos del cliente, lista para copiar. */
    public static function plantilla(string $texto, string $cliente, string $oferta, string $proyecto): string
    {
        return strtr($texto, ['{cliente}' => $cliente, '{oferta}' => $oferta, '{proyecto}' => $proyecto]);
    }
}
