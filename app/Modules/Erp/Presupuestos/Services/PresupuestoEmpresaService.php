<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Models\GastoDemo;
use App\Modules\Nucleo\Imputacion\CentroDeCosto;
use App\Modules\Nucleo\Imputacion\MomentoImputacion;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\ImputacionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MVP rápido (ficha t2-presupuesto-empresa, plan 2.13): lo que la empresa gasta fuera de los proyectos se presupuesta
 * por sede y mes, y ese presupuesto controla el gasto directo como el árbol controla el de un proyecto: saldo antes de
 * gastar, comprometido, ejecutado, el exceso con motivo, alertas al 75, 90 y 100 % y traspasos con motivo.
 */
final readonly class PresupuestoEmpresaService
{
    public const string PERMISO_VER = 'erp.presupuesto-empresa.ver';

    public const string PERMISO_GESTIONAR = 'erp.presupuesto-empresa.gestionar';

    public const array RUBROS = ['Alquiler y mantenimiento de local', 'Servicios (luz, agua, internet, telefonía)', 'Movilidad y viáticos', 'Útiles y suministros de oficina', 'Seguros', 'Software y licencias', 'Capacitación', 'Publicidad y ferias'];

    public const array UMBRALES = [75, 90, 100];

    /** @return array<int|string, string> id de sede (o 'empresa') => nombre */
    public function sedes(): array
    {
        $partyId = app(EmpresaService::class)->ficha()?->id;
        $sedes = $partyId === null ? [] : DB::table('party_sedes')->where('party_id', $partyId)->where('es_activa', true)->orderBy('id')->pluck('nombre', 'id')->all();

        return $sedes + ['empresa' => 'Empresa (de todas las sedes)'];
    }

    public function crear(int $anio, ?int $copiarDe = null, string $ajustePct = '0'): int
    {
        return DB::transaction(function () use ($anio, $copiarDe, $ajustePct): int {
            $id = DB::table('presupuestos_empresa')->insertGetId(['anio' => $anio, 'estado' => 'borrador', 'created_by' => auth()->id() ?? 1, 'created_at' => now()]);

            if ($copiarDe !== null) {
                $factor = bcadd('1', bcdiv($ajustePct, '100', 6), 6);

                foreach (DB::table('presupuesto_empresa_lineas')->where('presupuesto_empresa_id', $copiarDe)->get() as $l) {
                    $fila = ['presupuesto_empresa_id' => $id, 'sede_id' => $l->sede_id, 'rubro' => $l->rubro, 'nota' => $ajustePct === '0' ? $l->nota : "Copia del año anterior con {$ajustePct} % de ajuste", 'created_at' => now()];

                    foreach (range(1, 12) as $m) {
                        $fila[sprintf('m%02d', $m)] = bcadd(bcmul((string) $l->{sprintf('m%02d', $m)}, $factor, 6), '0.005', 2);
                    }

                    DB::table('presupuesto_empresa_lineas')->insert($fila);
                }
            }

            return $id;
        });
    }

    /** @param  array<int, string>  $meses  mes (1..12) => monto */
    public function guardarLinea(int $presupuestoId, ?int $sedeId, string $rubro, array $meses, ?string $nota): void
    {
        $this->exigirBorrador($presupuestoId);
        $fila = ['nota' => $nota, 'updated_at' => now()];

        foreach (range(1, 12) as $m) {
            $monto = (string) ($meses[$m] ?? '0');

            if (bccomp($monto, '0', 2) < 0) {
                throw new RuntimeException('Los montos no pueden ser negativos.');
            }

            $fila[sprintf('m%02d', $m)] = $monto;
        }

        DB::table('presupuesto_empresa_lineas')->updateOrInsert(['presupuesto_empresa_id' => $presupuestoId, 'sede_id' => $sedeId, 'rubro' => $rubro], $fila + ['created_at' => now()]);
    }

    public function aprobar(int $presupuestoId): void
    {
        $this->exigirBorrador($presupuestoId);
        DB::table('presupuestos_empresa')->where('id', $presupuestoId)->update(['estado' => 'aprobado', 'aprobado_at' => now(), 'updated_at' => now()]);
    }

    /** Mueve saldo presupuestado de una línea y mes a otra, con motivo. No se puede dejar en negativo lo que se mueve. */
    public function traspasar(int $desdeLinea, int $desdeMes, int $haciaLinea, int $haciaMes, string $monto, string $motivo): void
    {
        if (trim($motivo) === '' || bccomp($monto, '0', 2) <= 0) {
            throw new RuntimeException('El traspaso pide un monto mayor que cero y su motivo.');
        }

        DB::transaction(function () use ($desdeLinea, $desdeMes, $haciaLinea, $haciaMes, $monto, $motivo): void {
            $desde = DB::table('presupuesto_empresa_lineas')->lockForUpdate()->find($desdeLinea);
            $col = sprintf('m%02d', $desdeMes);

            if (bccomp((string) $desde->{$col}, $monto, 2) < 0) {
                throw new RuntimeException('La línea de origen no tiene ese saldo en ese mes.');
            }

            DB::table('presupuesto_empresa_lineas')->where('id', $desdeLinea)->update([$col => bcsub((string) $desde->{$col}, $monto, 2)]);
            DB::table('presupuesto_empresa_lineas')->where('id', $haciaLinea)->increment(sprintf('m%02d', $haciaMes), (float) $monto);
            DB::table('presupuesto_empresa_traspasos')->insert(['desde_linea_id' => $desdeLinea, 'desde_mes' => $desdeMes, 'hacia_linea_id' => $haciaLinea, 'hacia_mes' => $haciaMes,
                'monto' => $monto, 'motivo' => trim($motivo), 'created_by' => auth()->id() ?? 1, 'created_at' => now()]);
        });
    }

    /** Lo presupuestado, comprometido y ejecutado de una sede en un mes, y su saldo. */
    public function saldo(int $anio, ?int $sedeId, int $mes): array
    {
        $presupuestado = (string) (DB::table('presupuesto_empresa_lineas as l')->join('presupuestos_empresa as p', 'p.id', '=', 'l.presupuesto_empresa_id')
            ->where('p.anio', $anio)->where('p.estado', 'aprobado')->where(fn ($q) => $sedeId === null ? $q->whereNull('l.sede_id') : $q->where('l.sede_id', $sedeId))
            ->sum(sprintf('l.m%02d', $mes)) ?: '0');
        $gastos = DB::table('gastos_demo as g')->join('imputaciones as i', fn ($j) => $j->on('i.origen_id', '=', 'g.id')->where('i.origen_type', 'gasto_demo'))
            ->where('g.es_gasto_directo', true)->whereYear('g.fecha', $anio)->whereMonth('g.fecha', $mes)
            ->where(fn ($q) => $sedeId === null ? $q->whereNull('g.sede_id') : $q->where('g.sede_id', $sedeId))
            ->selectRaw('COALESCE(SUM(i.monto_comprometido), 0) AS comprometido, COALESCE(SUM(i.monto_ejecutado), 0) AS ejecutado')->first();

        return [
            'presupuestado' => bcadd($presupuestado, '0', 2), 'comprometido' => bcadd((string) $gastos->comprometido, '0', 2), 'ejecutado' => bcadd((string) $gastos->ejecutado, '0', 2),
            'saldo' => bcsub($presupuestado, (string) $gastos->comprometido, 2),
        ];
    }

    /**
     * El gasto directo (de la empresa, no de un proyecto): con su sede, rubro y centro de costo. Si pasa el saldo de la
     * sede en el mes, exige motivo; sin presupuesto aprobado en ese mes, no se carga (plan 2.13, D4).
     */
    public function gastoDirecto(?int $sedeId, string $rubro, int $centroCostoId, CarbonImmutable $fecha, string $monto, string $ejecutado, string $descripcion, string $proveedor, ?string $motivoExceso): GastoDemo
    {
        $s = $this->saldo((int) $fecha->format('Y'), $sedeId, (int) $fecha->format('n'));

        if (bccomp($s['presupuestado'], '0', 2) <= 0) {
            throw new RuntimeException('Esa sede no tiene presupuesto aprobado en '.$fecha->translatedFormat('F Y').': primero hay que presupuestarlo.');
        }

        $excede = bccomp($monto, $s['saldo'], 2) > 0;

        if ($excede && ($motivoExceso === null || trim($motivoExceso) === '')) {
            throw new RuntimeException('El gasto pasa el saldo de la sede en el mes (S/ '.number_format((float) $s['saldo'], 2).'): escribe el motivo del exceso.');
        }

        return DB::transaction(function () use ($sedeId, $rubro, $centroCostoId, $fecha, $monto, $ejecutado, $descripcion, $proveedor, $excede, $motivoExceso): GastoDemo {
            $gasto = GastoDemo::query()->create([
                'numero' => sprintf('GD-%s-%04d', $fecha->format('Y'), (int) GastoDemo::query()->max('id') + 1), 'descripcion' => $descripcion, 'proveedor' => $proveedor,
                'fecha' => $fecha->toDateString(), 'monto' => $monto, 'created_by' => auth()->id(), 'sede_id' => $sedeId, 'rubro' => $rubro,
                'centro_costo_id' => $centroCostoId, 'es_gasto_directo' => true, 'motivo_exceso' => $excede ? $motivoExceso : null,
            ]);
            $imputaciones = app(ImputacionService::class);
            $imputaciones->destinar($gasto, new CentroDeCosto($centroCostoId), $fecha, 'PEN');
            $imputaciones->mover($gasto, MomentoImputacion::Comprometido, $monto);

            if (bccomp($ejecutado, '0', 2) > 0) {
                $imputaciones->mover($gasto, MomentoImputacion::Ejecutado, $ejecutado);
            }

            return $gasto;
        });
    }

    /**
     * El comparativo por sede y mes: presupuestado, comprometido, ejecutado, variación y % de consumo, con el umbral
     * alcanzado (75, 90 o 100 %).
     *
     * @return list<array{sede: string, mes: int, presupuestado: string, comprometido: string, ejecutado: string, variacion: string, consumo: ?string, umbral: ?int}>
     */
    public function comparativo(int $anio, int $hastaMes): array
    {
        $filas = [];

        foreach ($this->sedes() as $id => $nombre) {
            for ($m = 1; $m <= $hastaMes; $m++) {
                $s = $this->saldo($anio, $id === 'empresa' ? null : (int) $id, $m);
                $consumo = bccomp($s['presupuestado'], '0', 2) > 0 ? bcmul(bcdiv($s['comprometido'], $s['presupuestado'], 6), '100', 1) : null;
                $umbral = null;

                foreach (self::UMBRALES as $u) {
                    if ($consumo !== null && bccomp($consumo, (string) $u, 1) >= 0) {
                        $umbral = $u;
                    }
                }

                $filas[] = ['sede' => $nombre, 'mes' => $m, ...$s, 'variacion' => bcsub($s['presupuestado'], $s['comprometido'], 2), 'consumo' => $consumo, 'umbral' => $umbral];
            }
        }

        return $filas;
    }

    private function exigirBorrador(int $presupuestoId): void
    {
        if (DB::table('presupuestos_empresa')->where('id', $presupuestoId)->value('estado') !== 'borrador') {
            throw new RuntimeException('El presupuesto aprobado ya no se edita: los cambios van por traspasos.');
        }
    }
}
