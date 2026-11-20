<?php

declare(strict_types=1);

namespace App\Modules\Erp\Personal\Services;

use App\Modules\Erp\Personal\Models\Asignacion;
use App\Modules\Erp\Personal\Models\Colaborador;
use App\Modules\Erp\Personal\Models\Tareo;
use App\Modules\Erp\Personal\Models\TareoLinea;
use App\Modules\Erp\Presupuestos\Services\PartidasService;
use App\Modules\Nucleo\Imputacion\MomentoImputacion;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ImputacionService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MVP rápido (ficha t4-personal-tareo, flujo 3): las horas de la gente. Cada persona se asigna a proyectos con un
 * porcentaje y una vigencia (se marca la sobreasignación); se tarea por semana, persona, día y partida al costo hora
 * estándar de su categoría (el mismo del APU); solo con habilitación de campo vigente. Al cerrar la semana, las horas
 * pasan a comprometido y ejecutado de cada partida a la vez; una semana cerrada no se edita.
 */
final readonly class PersonalService
{
    public const string PERMISO_VER = 'erp.personal.ver';

    public const string PERMISO_TAREAR = 'erp.personal.tarear';

    public const string PERMISO_CERRAR = 'erp.personal.cerrar';

    public const array DIAS = ['lu' => 'Lu', 'ma' => 'Ma', 'mi' => 'Mi', 'ju' => 'Ju', 'vi' => 'Vi', 'sa' => 'Sá'];

    public function asignar(int $colaboradorId, int $proyectoId, int $porcentaje, CarbonImmutable $desde, ?CarbonImmutable $hasta): Asignacion
    {
        if ($porcentaje < 1 || $porcentaje > 100) {
            throw new RuntimeException('La dedicación va de 1 a 100 %.');
        }

        $c = Colaborador::query()->findOrFail($colaboradorId);
        $a = Asignacion::query()->create(['colaborador_id' => $c->id, 'proyecto_id' => $proyectoId, 'porcentaje' => $porcentaje, 'desde' => $desde->toDateString(), 'hasta' => $hasta?->toDateString()]);

        return $a;
    }

    /** La dedicación total de una persona en una fecha (más de 100 % es sobreasignación). */
    public function dedicacion(int $colaboradorId, CarbonImmutable $fecha): int
    {
        return (int) Asignacion::query()->where('colaborador_id', $colaboradorId)->where('desde', '<=', $fecha->toDateString())
            ->where(fn ($q) => $q->whereNull('hasta')->orWhere('hasta', '>=', $fecha->toDateString()))->sum('porcentaje');
    }

    /** El tareo de la semana del proyecto (lunes); lo abre si no existe. */
    public function semana(int $proyectoId, CarbonImmutable $lunes): Tareo
    {
        return Tareo::query()->firstOrCreate(['proyecto_id' => $proyectoId, 'semana' => $lunes->startOfWeek()->toDateString()], ['estado' => 'abierto']);
    }

    /** @param  array<string, string>  $horas  día => horas */
    public function tarear(int $tareoId, int $colaboradorId, int $partidaId, array $horas, ?string $autorizacion = null): TareoLinea
    {
        $t = Tareo::query()->findOrFail($tareoId);
        $c = Colaborador::query()->findOrFail($colaboradorId);

        if ($t->estado !== 'abierto') {
            throw new RuntimeException('La semana está cerrada: se corrige con un ajuste.');
        }

        // Flujo 9 (F9): con la habilitación vencida, el tareo se bloquea salvo que lo autorice, con motivo, quien tiene ese permiso.
        if (($c->habilitado_hasta === null || $c->habilitado_hasta->lt($t->semana)) && ($autorizacion === null || trim($autorizacion) === '' || ! (auth()->user()?->can(PersonasService::PERMISO_AUTORIZAR) ?? false))) {
            throw new RuntimeException("{$c->nombres} no tiene su habilitación de campo (SCTR) vigente: no se le puede tarear en obra. Quien tiene el permiso puede autorizarlo con motivo.");
        }

        if (DB::table('partidas_control')->where('id', $partidaId)->value('proyecto_id') !== $t->proyecto_id) {
            throw new RuntimeException('Esa partida no es de este proyecto.');
        }

        $costo = (string) DB::table('categorias_personal')->where('id', $c->categoria_id)->value('costo_hora');
        $fila = ['costo_hora' => $costo, 'autorizacion' => $autorizacion];

        foreach (array_keys(self::DIAS) as $d) {
            $fila[$d] = (string) ($horas[$d] ?? '0');

            if (bccomp($fila[$d], '0', 1) < 0 || bccomp($fila[$d], '16', 1) > 0) {
                throw new RuntimeException('Las horas de un día van de 0 a 16.');
            }
        }

        return TareoLinea::query()->updateOrCreate(['tareo_id' => $t->id, 'colaborador_id' => $c->id, 'partida_control_id' => $partidaId], $fila);
    }

    /** Cierra la semana: cada línea pasa a comprometido y ejecutado de su partida a la vez. */
    public function cerrar(int $tareoId, User $quien): Tareo
    {
        return DB::transaction(function () use ($tareoId, $quien): Tareo {
            $t = Tareo::query()->with('lineas')->lockForUpdate()->findOrFail($tareoId);

            if ($t->estado !== 'abierto' || $t->lineas->isEmpty()) {
                throw new RuntimeException($t->estado !== 'abierto' ? 'La semana ya está cerrada.' : 'La semana no tiene horas.');
            }

            $imputaciones = app(ImputacionService::class);

            foreach ($t->lineas as $l) {
                $imputaciones->destinar($l, app(PartidasService::class)->destino($l->partida_control_id), $t->semana->addDays(5), 'PEN');
                $imputaciones->mover($l, MomentoImputacion::Comprometido, $l->costo());
                $imputaciones->mover($l, MomentoImputacion::Ejecutado, $l->costo());
            }

            $t->update(['estado' => 'cerrado', 'cerrado_por' => $quien->id, 'cerrado_at' => Calendario::ahora()]);

            return $t->refresh();
        });
    }
}
