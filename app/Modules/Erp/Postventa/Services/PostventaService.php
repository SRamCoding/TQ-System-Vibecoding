<?php

declare(strict_types=1);

namespace App\Modules\Erp\Postventa\Services;

use App\Modules\Crm\Oportunidades\Services\DatosOportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use App\Modules\Nucleo\Numeracion\ReinicioNumeracion;
use App\Modules\Nucleo\Services\NumeracionService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MVP rápido (ficha t6-postventa, flujo 8): empieza siempre por identificar el equipo (su serie dice a quién se compró,
 * cuándo se instaló y si sigue en garantía). El ticket vence según su nivel de servicio; en garantía del fabricante se
 * gestiona con él; fuera de garantía no se atiende sin cotización aceptada y vuelve al CRM como oportunidad.
 */
final readonly class PostventaService
{
    public const string PERMISO_VER = 'erp.postventa.ver';

    public const string PERMISO_ATENDER = 'erp.postventa.atender';

    /** Horas de atención por prioridad (SLA). */
    public const array SLA = ['alta' => 8, 'media' => 24, 'baja' => 72];

    /** Ingreso de series al almacén con la recepción de una orden. */
    public function ingresar(int $productoId, array $series, ?int $ordenId, CarbonImmutable $fecha, string $ubicacion): void
    {
        foreach ($series as $s) {
            DB::table('series')->insert(['producto_id' => $productoId, 'serie' => $s, 'orden_id' => $ordenId, 'ingreso' => $fecha->toDateString(), 'ubicacion' => $ubicacion,
                'estado' => 'en_almacen', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** Despacho e instalación: desde aquí corre la garantía al cliente (por omisión, 24 meses desde la instalación). */
    public function instalar(string $serie, int $proyectoId, CarbonImmutable $despacho, string $lugar, int $mesesGarantia = 24, ?int $mesesFabricante = 36): void
    {
        $n = DB::table('series')->where('serie', $serie)->update(['proyecto_id' => $proyectoId, 'despacho' => $despacho->toDateString(), 'instalada_en' => $lugar, 'estado' => 'instalada',
            'garantia_hasta' => $despacho->addMonths($mesesGarantia)->toDateString(), 'garantia_fabricante_hasta' => $mesesFabricante === null ? null : $despacho->addMonths($mesesFabricante)->toDateString(), 'updated_at' => now()]);

        if ($n === 0) {
            throw new RuntimeException("No hay ninguna serie {$serie} en el almacén.");
        }
    }

    /** La vida completa de un equipo por su serie (completa o la parte final). */
    public function rastrear(string $texto): ?object
    {
        $texto = trim($texto);

        return $texto === '' ? null : DB::table('series as s')->join('productos as p', 'p.id', '=', 's.producto_id')->leftJoin('ordenes as o', 'o.id', '=', 's.orden_id')->leftJoin('proyectos as y', 'y.id', '=', 's.proyecto_id')
            ->where(fn ($q) => $q->where('s.serie', $texto)->orWhere('s.serie', 'ilike', "%{$texto}"))
            ->first(['s.*', 'p.codigo as producto', 'p.descripcion', 'o.codigo as orden', 'o.proveedor_nombre', 'y.codigo as proyecto', 'y.nombre as proyecto_nombre']);
    }

    /** El estado de garantía de un equipo hoy. */
    public function garantia(?object $serie): string
    {
        $hoy = Calendario::hoy();

        return match (true) {
            $serie === null || $serie->garantia_hasta === null => 'vencida',
            $serie->garantia_hasta >= $hoy => 'vigente',
            $serie->garantia_fabricante_hasta !== null && $serie->garantia_fabricante_hasta >= $hoy => 'fabricante',
            default => 'vencida',
        };
    }

    public function abrir(int $cuentaId, ?string $serie, string $equipo, string $reclamo, string $prioridad, ?int $responsableId, ?CarbonImmutable $cuando = null): int
    {
        if (! isset(self::SLA[$prioridad])) {
            throw new RuntimeException('Prioridad alta, media o baja.');
        }

        $s = $serie === null ? null : $this->rastrear($serie);
        $abierto = $cuando ?? Calendario::ahora();

        return DB::transaction(fn (): int => (int) DB::table('tickets_postventa')->insertGetId([
            'codigo' => app(NumeracionService::class)->siguiente('ticket_postventa', new FormatoNumeracion('TK', ReinicioNumeracion::Nunca), Calendario::ahora()),
            'cuenta_id' => $cuentaId, 'serie_id' => $s?->id, 'equipo' => $s === null ? $equipo : "{$s->descripcion} · serie {$s->serie}", 'proyecto_id' => $s?->proyecto_id,
            'reclamo' => trim($reclamo), 'prioridad' => $prioridad, 'abierto_at' => $abierto, 'vence_at' => $abierto->addHours(self::SLA[$prioridad]),
            'garantia' => $this->garantia($s), 'estado' => $this->garantia($s) === 'vencida' ? 'por_cotizar' : 'abierto', 'responsable_user_id' => $responsableId, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    public function cambiarEstado(int $ticketId, string $estado, ?string $solucion = null): void
    {
        DB::table('tickets_postventa')->where('id', $ticketId)->update(['estado' => $estado, 'solucion' => $solucion ?? DB::raw('solucion'),
            'resuelto_at' => in_array($estado, ['resuelto', 'cerrado'], true) ? Calendario::ahora() : null, 'updated_at' => now()]);
    }

    public function visita(int $ticketId, CarbonImmutable $fecha, string $tecnico, string $horas, string $materiales, ?string $nota): void
    {
        DB::table('visitas_tecnicas')->insert(['ticket_id' => $ticketId, 'fecha' => $fecha->toDateString(), 'tecnico' => $tecnico, 'horas' => $horas, 'materiales' => $materiales, 'nota' => $nota, 'created_at' => now(), 'updated_at' => now()]);

        if (DB::table('tickets_postventa')->where('id', $ticketId)->value('estado') === 'abierto') {
            $this->cambiarEstado($ticketId, 'en_atencion');
        }
    }

    /** Fuera de garantía: vuelve al CRM como oportunidad (no se atiende sin cotización aceptada). */
    public function cotizar(int $ticketId, string $monto, User $quien): int
    {
        return DB::transaction(function () use ($ticketId, $monto, $quien): int {
            $t = DB::table('tickets_postventa')->lockForUpdate()->find($ticketId);

            if ($t->oportunidad_id !== null) {
                return (int) $t->oportunidad_id;
            }

            $o = app(OportunidadesService::class)->crear($t->cuenta_id, new DatosOportunidad("Reparación fuera de garantía · {$t->codigo}", "{$t->equipo}: {$t->reclamo}", $monto,
                (int) DB::table('monedas')->where('codigo', 'PEN')->value('id'), Calendario::ahora()->addDays(15), (int) DB::table('lineas_solucion')->where('codigo', 'VIDEOVIGILANCIA')->value('id'),
                (int) DB::table('origenes_comerciales')->where('codigo', 'REFERENCIA')->value('id'), $quien->id), [], $quien);
            DB::table('tickets_postventa')->where('id', $ticketId)->update(['oportunidad_id' => $o->id, 'estado' => 'por_cotizar', 'updated_at' => now()]);

            return $o->id;
        });
    }
}
