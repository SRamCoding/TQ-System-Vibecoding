<?php

declare(strict_types=1);

namespace App\Modules\Erp\Personal\Services;

use App\Modules\Erp\Personal\Models\Colaborador;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MVP rápido (ficha t7-personas, flujo 9): las personas de la necesidad a la salida. La demanda se prevé con 90 días; se
 * selecciona y se contrata; la habilitación de campo son documentos con vencimiento (el SCTR y el examen médico
 * definen hasta cuándo se puede tarear); la planilla del mes sale de los contratos y del tareo (ejemplo simplificado:
 * ONP 13 % o AFP 11.84 %, EsSalud 9 %); las certificaciones vencen con alerta; la salida arma su lista de pendientes.
 */
final readonly class PersonasService
{
    public const string PERMISO_AUTORIZAR = 'erp.personal.autorizar';

    public const string PERMISO_PLANILLA = 'erp.personal.planilla';

    public const array APORTE = ['onp' => '0.13', 'afp' => '0.1184'];

    public const string ESSALUD = '0.09';

    /** Horas de un mes de trabajo, para el costo real por hora. */
    public const int HORAS_MES = 192;

    public function documento(int $colaboradorId, string $tipo, string $nombre, ?string $emisor, ?string $vence): void
    {
        DB::table('colaborador_documentos')->insert(['colaborador_id' => $colaboradorId, 'tipo' => $tipo, 'nombre' => $nombre, 'emisor' => $emisor, 'vence' => $vence, 'created_at' => now(), 'updated_at' => now()]);
        $this->recalcularHabilitacion($colaboradorId);
    }

    /** La habilitación de campo vale hasta el primer vencimiento entre el último SCTR y el último examen médico. */
    public function recalcularHabilitacion(int $colaboradorId): void
    {
        $sctr = DB::table('colaborador_documentos')->where('colaborador_id', $colaboradorId)->where('tipo', 'sctr')->max('vence');
        $emo = DB::table('colaborador_documentos')->where('colaborador_id', $colaboradorId)->where('tipo', 'emo')->max('vence');
        $hasta = $sctr === null ? null : ($emo === null ? $sctr : min($sctr, $emo));
        Colaborador::query()->whereKey($colaboradorId)->update(['habilitado_hasta' => $hasta]);
    }

    /**
     * La demanda de los próximos 90 días por categoría: personas activas, las que ya están asignadas al 100 %, las
     * libres y lo que piden las vacantes abiertas.
     *
     * @return list<array{categoria: string, activos: int, ocupados: int, libres: int, pedidos: int, brecha: int}>
     */
    public function demanda(): array
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $filas = [];

        foreach (DB::table('categorias_personal')->orderBy('id')->get() as $cat) {
            $activos = Colaborador::query()->where('categoria_id', $cat->id)->where('es_activo', true)->pluck('id');
            $ocupados = $activos->filter(fn (int $id): bool => app(PersonalService::class)->dedicacion($id, $hoy->addWeek()) >= 100)->count();
            $pedidos = (int) DB::table('vacantes')->where('categoria_id', $cat->id)->where('estado', 'abierta')->where('desde', '<=', $hoy->addDays(90)->toDateString())->sum('cantidad');
            $libres = $activos->count() - $ocupados;
            $filas[] = ['categoria' => $cat->nombre, 'activos' => $activos->count(), 'ocupados' => $ocupados, 'libres' => $libres, 'pedidos' => $pedidos, 'brecha' => max(0, $pedidos - $libres)];
        }

        return $filas;
    }

    public function vacante(int $categoriaId, ?int $proyectoId, int $cantidad, string $desde, ?string $perfil): int
    {
        return (int) DB::table('vacantes')->insertGetId(['categoria_id' => $categoriaId, 'proyecto_id' => $proyectoId, 'cantidad' => $cantidad, 'desde' => $desde, 'perfil' => $perfil, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function candidato(int $vacanteId, string $nombres, string $dni, ?string $nota): int
    {
        return (int) DB::table('candidatos')->insertGetId(['vacante_id' => $vacanteId, 'nombres' => $nombres, 'dni' => $dni, 'nota' => $nota, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Contrata al candidato: nace el colaborador con su contrato; la vacante se cubre al completar su cantidad. */
    public function contratar(int $candidatoId, string $remuneracion, string $regimen, CarbonImmutable $ingreso, string $sexo = 'hombre'): int
    {
        return DB::transaction(function () use ($candidatoId, $remuneracion, $regimen, $ingreso, $sexo): int {
            $c = DB::table('candidatos')->lockForUpdate()->find($candidatoId);
            $v = DB::table('vacantes')->find($c->vacante_id);

            if ($c->estado === 'contratado') {
                throw new RuntimeException('Ya fue contratado.');
            }

            $id = Colaborador::query()->create(['nombres' => $c->nombres, 'dni' => $c->dni, 'categoria_id' => $v->categoria_id, 'vinculo' => 'planilla', 'sexo' => $sexo, 'jornada' => TareoService::jornadaPropuesta('planilla'), 'remuneracion' => $remuneracion,
                'regimen' => $regimen, 'ingreso' => $ingreso->toDateString(), 'es_activo' => true])->id;
            DB::table('candidatos')->where('id', $candidatoId)->update(['estado' => 'contratado', 'updated_at' => now()]);

            if (DB::table('candidatos')->where('vacante_id', $v->id)->where('estado', 'contratado')->count() >= $v->cantidad) {
                DB::table('vacantes')->where('id', $v->id)->update(['estado' => 'cubierta', 'updated_at' => now()]);
            }

            if ($v->proyecto_id !== null) {
                app(PersonalService::class)->asignar($id, $v->proyecto_id, 100, $ingreso, null);
            }

            return $id;
        });
    }

    /** Calcula la planilla del periodo con los contratos vigentes y las horas tareadas del mes. */
    public function calcularPlanilla(string $periodo): int
    {
        return DB::transaction(function () use ($periodo): int {
            $p = DB::table('planillas')->where('periodo', $periodo)->first();

            if ($p?->estado === 'cerrada') {
                throw new RuntimeException('La planilla de ese mes ya está cerrada.');
            }

            $id = $p?->id ?? (int) DB::table('planillas')->insertGetId(['periodo' => $periodo, 'estado' => 'borrador', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('planilla_lineas')->where('planilla_id', $id)->delete();
            [$y, $m] = array_map('intval', explode('-', $periodo));
            $fin = CarbonImmutable::create($y, $m, 1)->endOfMonth()->toDateString();

            foreach (Colaborador::query()->whereNotNull('remuneracion')->where(fn ($q) => $q->whereNull('salida')->orWhere('salida', '>=', "{$periodo}-01"))->where(fn ($q) => $q->whereNull('ingreso')->orWhere('ingreso', '<=', $fin))->get() as $c) {
                $rem = (string) $c->remuneracion;
                $aporte = bcadd(bcmul($rem, self::APORTE[$c->regimen] ?? self::APORTE['onp'], 4), '0.005', 2);
                $essalud = bcadd(bcmul($rem, self::ESSALUD, 4), '0.005', 2);
                $horas = DB::table('tareo_lineas as l')->join('tareos as t', 't.id', '=', 'l.tareo_id')->where('l.colaborador_id', $c->id)->whereYear('t.semana', $y)->whereMonth('t.semana', $m)
                    ->selectRaw('COALESCE(SUM(lu + ma + mi + ju + vi + sa), 0) AS h')->value('h');
                DB::table('planilla_lineas')->insert(['planilla_id' => $id, 'colaborador_id' => $c->id, 'regimen' => $c->regimen, 'remuneracion' => $rem, 'aporte' => $aporte,
                    'neto' => bcsub($rem, $aporte, 2), 'essalud' => $essalud, 'costo_empresa' => bcadd($rem, $essalud, 2), 'horas_tareadas' => $horas]);
            }

            return $id;
        });
    }

    public function cerrarPlanilla(int $planillaId): void
    {
        DB::table('planillas')->where('id', $planillaId)->where('estado', 'borrador')->update(['estado' => 'cerrada', 'cerrada_at' => now(), 'updated_at' => now()]);
    }

    /**
     * La diferencia entre el costo real de las horas (costo empresa ÷ 192) y su costo estándar, por proyecto.
     *
     * @return list<array{proyecto: string, horas: string, estandar: string, real: string, diferencia: string}>
     */
    public function diferenciaPorProyecto(int $planillaId): array
    {
        $p = DB::table('planillas')->find($planillaId);
        [$y, $m] = array_map('intval', explode('-', $p->periodo));
        $real = DB::table('planilla_lineas')->where('planilla_id', $planillaId)->pluck('costo_empresa', 'colaborador_id');
        $filas = [];

        foreach (DB::table('tareo_lineas as l')->join('tareos as t', 't.id', '=', 'l.tareo_id')->join('proyectos as y', 'y.id', '=', 't.proyecto_id')
            ->whereYear('t.semana', $y)->whereMonth('t.semana', $m)->get(['y.codigo', 'l.colaborador_id', 'l.costo_hora', DB::raw('(lu + ma + mi + ju + vi + sa) AS horas')]) as $l) {
            $horaReal = isset($real[$l->colaborador_id]) ? bcdiv((string) $real[$l->colaborador_id], (string) self::HORAS_MES, 4) : (string) $l->costo_hora;
            $f = &$filas[$l->codigo];
            $f ??= ['proyecto' => $l->codigo, 'horas' => '0', 'estandar' => '0', 'real' => '0'];
            $f['horas'] = bcadd($f['horas'], (string) $l->horas, 1);
            $f['estandar'] = bcadd($f['estandar'], bcmul((string) $l->horas, (string) $l->costo_hora, 4), 2);
            $f['real'] = bcadd($f['real'], bcmul((string) $l->horas, $horaReal, 4), 2);
            unset($f);
        }

        return array_values(array_map(fn (array $f): array => [...$f, 'diferencia' => bcsub($f['real'], $f['estandar'], 2)], $filas));
    }

    /**
     * Lo que hay que resolver cuando alguien se va (v4 paso 7).
     *
     * @return list<array{que: string, detalle: string, resuelto: bool}>
     */
    public function pendientesDeSalida(int $colaboradorId): array
    {
        $c = Colaborador::query()->findOrFail($colaboradorId);
        $hoy = Calendario::hoy();
        $asign = DB::table('asignaciones as a')->join('proyectos as p', 'p.id', '=', 'a.proyecto_id')->where('a.colaborador_id', $c->id)->where(fn ($q) => $q->whereNull('a.hasta')->orWhere('a.hasta', '>=', $hoy))->pluck('p.codigo');
        $uid = $c->user_id;
        $partidas = $uid === null ? 0 : DB::table('partidas_control')->where('responsable_user_id', $uid)->where('es_activa', true)->count();
        $reqs = $uid === null ? 0 : DB::table('requerimientos')->where('solicitante_user_id', $uid)->whereIn('estado', ['borrador', 'por_aprobar'])->count();
        $cuentas = $uid === null ? 0 : DB::table('cuentas')->where('ejecutivo_user_id', $uid)->count();
        $fondos = DB::table('fondos')->where('responsable', $c->nombres)->where('estado', 'abierto')->count();
        $activo = $uid !== null && (bool) DB::table('users')->where('id', $uid)->value('es_activo');

        return [
            ['que' => 'Liberar sus asignaciones', 'detalle' => $asign->isEmpty() ? 'sin asignaciones vigentes' : $asign->implode(', '), 'resuelto' => $asign->isEmpty()],
            ['que' => 'Revocar su acceso al sistema', 'detalle' => $uid === null ? 'no tiene usuario' : ($activo ? 'usuario activo' : 'usuario desactivado'), 'resuelto' => ! $activo],
            ['que' => 'Traspasar sus partidas como responsable', 'detalle' => "{$partidas} partidas", 'resuelto' => $partidas === 0],
            ['que' => 'Traspasar sus requerimientos abiertos', 'detalle' => "{$reqs} requerimientos", 'resuelto' => $reqs === 0],
            ['que' => 'Traspasar sus clientes en el CRM', 'detalle' => "{$cuentas} cuentas", 'resuelto' => $cuentas === 0],
            ['que' => 'Rendir sus fondos', 'detalle' => "{$fondos} fondos abiertos", 'resuelto' => $fondos === 0],
            ['que' => 'Documentar lo que sabía', 'detalle' => 'nota de traspaso en su salida', 'resuelto' => false],
        ];
    }

    /** Registra la salida: libera sus asignaciones, revoca su acceso y calcula su liquidación (simplificada). */
    public function salida(int $colaboradorId, CarbonImmutable $fecha, string $motivo): string
    {
        return DB::transaction(function () use ($colaboradorId, $fecha, $motivo): string {
            $c = Colaborador::query()->lockForUpdate()->findOrFail($colaboradorId);
            DB::table('asignaciones')->where('colaborador_id', $c->id)->where(fn ($q) => $q->whereNull('hasta')->orWhere('hasta', '>', $fecha->toDateString()))->update(['hasta' => $fecha->toDateString(), 'updated_at' => now()]);

            if ($c->user_id !== null) {
                DB::table('users')->where('id', $c->user_id)->update(['es_activo' => false]);
            }

            // Simplificado: CTS y vacaciones truncas, una remuneración por año cada una, proporcional a los meses del año.
            $meses = max(1, (int) $fecha->month);
            $liq = $c->remuneracion === null ? '0.00' : bcadd(bcmul(bcdiv(bcmul((string) $c->remuneracion, (string) $meses, 4), '12', 4), '2', 4), '0.005', 2);
            $c->update(['salida' => $fecha->toDateString(), 'motivo_salida' => $motivo, 'liquidacion' => $liq, 'es_activo' => false]);

            return $liq;
        });
    }
}
