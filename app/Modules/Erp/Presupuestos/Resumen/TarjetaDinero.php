<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Resumen;

use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Services\ComparacionOfertado;
use App\Modules\Erp\Presupuestos\Services\DineroDelProyecto;
use App\Modules\Erp\Presupuestos\Services\SaldoPartida;
use App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\Pages\VerProyecto;
use App\Modules\Erp\Proyectos\Resumen\ContenidoTarjeta;
use App\Modules\Erp\Proyectos\Resumen\FilaDesglose;
use App\Modules\Erp\Proyectos\Resumen\LineaResumen;
use App\Modules\Erp\Proyectos\Resumen\SeccionResumen;
use App\Modules\Erp\Proyectos\Resumen\TarjetaResumen;
use App\Modules\Nucleo\Models\User;

/**
 * «¿Cuánto estamos ganando?» (plan 2.61, D10 y D11): presupuestado, comprometido, ejecutado, pagado, lo cargado a la
 * PC-99 y la proyección al cierre con su semáforo; con la venta del contrato, también el margen. Cada monto se toca y
 * muestra su desglose por partida. Lleva costos (D3).
 */
final class TarjetaDinero implements TarjetaResumen
{
    /** El identificador de Filament de la pestaña «Ofertado vs. presupuesto» en la dirección. */
    public const string PESTANA_COMPARACION = 'ofertado-vs-presupuesto::tab';

    public function clave(): string
    {
        return 'dinero';
    }

    public function seccion(): SeccionResumen
    {
        return SeccionResumen::Dinero;
    }

    public function orden(): int
    {
        return 10;
    }

    public function exigeCostos(): bool
    {
        return true;
    }

    public function contenido(int $proyectoId, User $usuario): ?ContenidoTarjeta
    {
        if (! $usuario->can(PresupuestosServiceProvider::PERMISO_VER) || ($d = app(DineroDelProyecto::class)->de($proyectoId)) === null) {
            return null;
        }

        $porPartida = fn (string $campo): array => array_map(
            fn (array $p): FilaDesglose => new FilaDesglose("{$p['codigo']} · {$p['nombre']}", Soles::texto($p['saldo']->{$campo})),
            array_values(array_filter($d['partidas'], fn (array $p): bool => bccomp($p['saldo']->{$campo}, '0', 2) !== 0)),
        );
        $signo = bccomp($d['desvio'], '0', 2) > 0 ? '+' : '';
        $lineas = [];

        if ($d['venta'] !== null) {
            $lineas[] = new LineaResumen('Vendido', Soles::texto($d['venta']));
            $lineas[] = new LineaResumen('Margen previsto', Soles::texto((string) $d['margen_previsto']));
            $lineas[] = new LineaResumen('Margen proyectado', Soles::texto((string) $d['margen_proyectado']), $d['tono']);
        }

        // MV-008: cuánto se movió el presupuesto respecto de lo ofertado; cada fila lleva a la pestaña de la comparación.
        if (($c = app(ComparacionOfertado::class)->de($proyectoId)) !== null) {
            $url = VerProyecto::getUrl(['record' => $proyectoId]).'?pestana='.self::PESTANA_COMPARACION;
            $cambios = array_values(array_filter($c['filas'], fn (array $f): bool => bccomp($f['diferencia'], '0', 2) !== 0));
            $lineas[] = new LineaResumen(
                'Presupuesto vs. ofertado',
                (bccomp($c['diferencia'], '0', 2) > 0 ? '+' : '').Soles::texto($c['diferencia']),
                bccomp($c['diferencia'], '0', 2) > 0 ? 'warning' : null,
                $cambios === [] ? [new FilaDesglose('Sin cambios: ver la comparación', '', $url)] : array_map(fn (array $f): FilaDesglose => new FilaDesglose("{$f['codigo']} · {$f['descripcion']}", (bccomp($f['diferencia'], '0', 2) > 0 ? '+' : '').Soles::texto($f['diferencia']), $url), $cambios),
                "{$c['vigente']} contra lo ofertado {$c['ofertado']}",
            );
        }

        array_push(
            $lineas,
            new LineaResumen('Presupuestado', Soles::texto($d['presupuestado']), desglose: $porPartida('presupuestado')),
            new LineaResumen('Comprometido', Soles::texto($d['comprometido']), desglose: $porPartida('comprometido')),
            new LineaResumen('Ejecutado', Soles::texto($d['ejecutado']), desglose: $porPartida('ejecutado')),
            new LineaResumen('Pagado', Soles::texto($d['pagado']), desglose: $porPartida('pagado')),
            new LineaResumen('Gastos no presupuestados (PC-99)', Soles::texto($d['no_presupuestado']), bccomp($d['no_presupuestado'], '0', 2) > 0 ? 'warning' : null, array_values(array_filter(
                $porPartida('comprometido'),
                fn (FilaDesglose $f): bool => str_starts_with($f->etiqueta, 'PC-99'),
            ))),
            new LineaResumen('Proyección al cierre', Soles::texto($d['proyeccion']), $d['tono'], self::desgloseProyeccion($d['partidas'], $d['por_repartir']), "{$signo}{$d['desvio']} puntos sobre lo presupuestado"),
        );

        return new ContenidoTarjeta('Costo del proyecto', $lineas);
    }

    /**
     * @param  list<array{id: int, codigo: string, nombre: string, saldo: SaldoPartida, es_no_presupuestado: bool}>  $partidas
     * @return list<FilaDesglose>
     */
    private static function desgloseProyeccion(array $partidas, string $porRepartir): array
    {
        $filas = array_map(function (array $p): FilaDesglose {
            $s = $p['saldo'];
            $mayor = $p['es_no_presupuestado'] || bccomp($s->comprometido, $s->presupuestado, 2) > 0 ? $s->comprometido : $s->presupuestado;

            return new FilaDesglose("{$p['codigo']} · {$p['nombre']}", Soles::texto($mayor));
        }, $partidas);

        if (bccomp($porRepartir, '0', 2) > 0) {
            $filas[] = new FilaDesglose('Falta repartir en partidas', Soles::texto($porRepartir));
        }

        return $filas;
    }
}
