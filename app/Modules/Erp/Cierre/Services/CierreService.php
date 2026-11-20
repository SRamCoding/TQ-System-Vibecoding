<?php

declare(strict_types=1);

namespace App\Modules\Erp\Cierre\Services;

use App\Modules\Erp\Contratos\Services\ContratosService;
use App\Modules\Erp\Finanzas\Services\FinanzasService;
use App\Modules\Erp\Presupuestos\Services\DineroDelProyecto;
use App\Modules\Erp\Proyectos\Condiciones\VerificacionesCierre;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\TransicionesService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Illuminate\Support\Facades\DB;

/**
 * MVP rápido (ficha t9-cierre, flujo 6): el proyecto termina cuando no queda nada abierto. La lista de verificación,
 * el cierre, lo que queda congelado (presupuesto contra real y margen real), lo aprendido y la devolución de la garantía.
 */
final readonly class CierreService
{
    public const string PERMISO_GESTIONAR = 'erp.cierre.gestionar';

    /** Las verificaciones del v4 (paso 2). Bloquean: órdenes, material, tareo, facturas retenidas y valorizaciones en borrador. */
    public static function registrarVerificaciones(VerificacionesCierre $v): void
    {
        $v->registrar('conformidad', function (int $id): array {
            $h = DB::table('contrato_hitos as h')->join('contratos as c', 'c.id', '=', 'h.contrato_id')->where('c.proyecto_id', $id)->orderByDesc('h.orden')->first(['h.*']);

            return ['que' => 'Conformidad final registrada', 'detalle' => $h === null ? 'sin contrato' : ($h->fecha_real ? 'el '.date('d/m/Y', strtotime($h->fecha_real)) : 'pendiente'), 'resuelto' => $h === null || $h->fecha_real !== null, 'bloquea' => false];
        });
        $v->registrar('ordenes', function (int $id): array {
            $o = DB::table('ordenes')->where('proyecto_id', $id)->whereIn('estado', ['borrador', 'aprobada', 'recibida_parcial'])->get(['codigo', 'estado']);

            return ['que' => 'Órdenes abiertas', 'detalle' => $o->isEmpty() ? 'ninguna' : $o->map(fn ($x): string => $x->codigo.' '.str_replace('_', ' ', $x->estado))->implode(', '), 'resuelto' => $o->isEmpty(), 'bloquea' => true];
        });
        $v->registrar('material', function (int $id): array {
            $r = (float) DB::table('movimientos_almacen')->where('proyecto_id', $id)->where('tipo', 'reserva')->sum('cantidad') - (float) DB::table('movimientos_almacen')->where('proyecto_id', $id)->whereIn('tipo', ['salida', 'despacho'])->sum('cantidad');

            return ['que' => 'Material asignado sin despachar', 'detalle' => $r > 0 ? rtrim(rtrim(number_format($r, 2, '.', ''), '0'), '.').' unidades reservadas' : 'ninguno', 'resuelto' => $r <= 0, 'bloquea' => true];
        });
        $v->registrar('tareo', function (int $id): array {
            $t = DB::table('tareos')->where('proyecto_id', $id)->where('estado', 'abierto')->pluck('semana');

            return ['que' => 'Tareo sin cerrar', 'detalle' => $t->isEmpty() ? 'ninguno' : 'semana del '.$t->map(fn ($s): string => date('d/m', strtotime($s)))->implode(', '), 'resuelto' => $t->isEmpty(), 'bloquea' => true];
        });
        $v->registrar('retenidas', function (int $id): array {
            $n = DB::table('comprobantes')->where('proyecto_id', $id)->where('clase', 'compra')->whereNotIn('estado', ['pagado', 'anulado'])->where(fn ($q) => $q->where('estado', 'retenido')->orWhere('cruce', 'like', '%dif%'))->count();

            return ['que' => 'Facturas de proveedor retenidas', 'detalle' => $n === 0 ? 'ninguna' : "{$n}", 'resuelto' => $n === 0, 'bloquea' => true];
        });
        $v->registrar('valorizaciones', function (int $id): array {
            $n = DB::table('valorizaciones as v')->join('contratos as c', 'c.id', '=', 'v.contrato_id')->where('c.proyecto_id', $id)->where('v.estado', 'borrador')->count();

            return ['que' => 'Valorizaciones en borrador', 'detalle' => $n === 0 ? 'ninguna' : "{$n}", 'resuelto' => $n === 0, 'bloquea' => true];
        });
        $v->registrar('venta', function (int $id): array {
            $n = DB::table('comprobantes')->where('proyecto_id', $id)->where('clase', 'venta')->where('estado', '!=', 'anulado')->count();

            return ['que' => 'Comprobante de venta emitido', 'detalle' => $n === 0 ? 'ninguno todavía' : "{$n} emitidos", 'resuelto' => $n > 0, 'bloquea' => false];
        });
    }

    public function cerrar(int $proyectoId, User $usuario, ?string $comentario): void
    {
        $p = Proyecto::query()->findOrFail($proyectoId);
        app(TransicionesService::class)->transicionar($p, 'cerrar', $p->version, $usuario, null, $comentario);
    }

    /** Congela el presupuesto contra real y el margen real (se llama al cerrarse el proyecto; se puede rehacer). */
    public function congelar(int $proyectoId): void
    {
        $d = app(DineroDelProyecto::class)->de($proyectoId);
        $r = app(FinanzasService::class)->resultado($proyectoId);
        $partidas = array_map(fn (array $p): array => ['codigo' => $p['codigo'], 'nombre' => $p['nombre'], 'presupuestado' => $p['saldo']->presupuestado, 'ejecutado' => $p['saldo']->ejecutado,
            'diferencia' => bcsub($p['saldo']->ejecutado, $p['saldo']->presupuestado, 2)], $d['partidas'] ?? []);
        $base = $r['vendido'] ?? $r['facturado'];
        $margen = bcsub((string) $base, $r['costo'], 2);
        $existente = DB::table('proyecto_cierres')->where('proyecto_id', $proyectoId)->first();
        $fila = ['fecha' => $existente->fecha ?? Calendario::hoy(), 'vendido' => $r['vendido'], 'facturado' => $r['facturado'], 'presupuestado' => $d['presupuestado'] ?? '0', 'costo' => $r['costo'],
            'margen_real' => $margen, 'margen_pct' => bccomp((string) $base, '0', 2) > 0 ? bcmul(bcdiv($margen, (string) $base, 6), '100', 1) : null, 'partidas' => json_encode($partidas, JSON_UNESCAPED_UNICODE), 'updated_at' => now()];
        DB::table('proyecto_cierres')->updateOrInsert(['proyecto_id' => $proyectoId], $existente === null ? [...$fila, 'created_at' => now()] : $fila);
    }

    public function lecciones(int $proyectoId, string $texto): void
    {
        DB::table('proyecto_cierres')->where('proyecto_id', $proyectoId)->update(['lecciones' => $texto, 'updated_at' => now()]);
    }

    public function devolverGarantia(int $garantiaId): void
    {
        app(ContratosService::class)->cambiarEstadoGarantia($garantiaId, 'devuelta');
    }
}
