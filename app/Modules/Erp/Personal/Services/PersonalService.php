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
use Illuminate\Support\Facades\Storage;
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

    public const array DIAS = ['lu' => 'Lu', 'ma' => 'Ma', 'mi' => 'Mi', 'ju' => 'Ju', 'vi' => 'Vi', 'sa' => 'Sá', 'do' => 'Do'];

    /** Observación 6.7 (MV-067): el sexo elige el avatar por defecto de quien no tiene foto. */
    public const array SEXOS = ['hombre' => 'Hombre', 'mujer' => 'Mujer'];

    /** Las fotos de perfil del personal, en el disco privado de adjuntos (se sirven por `/personal/fotos/{id}`). */
    public const string DISCO_FOTOS = 'adjuntos';

    /** Guarda la foto ya recortada (cuadrada) del colaborador y borra la anterior. */
    public function cambiarFoto(int $colaboradorId, string $contenido, string $extension): void
    {
        $ruta = 'colaboradores/'.$colaboradorId.'-'.bin2hex(random_bytes(4)).'.'.$extension;
        Storage::disk(self::DISCO_FOTOS)->put($ruta, $contenido);
        $anterior = DB::table('colaboradores')->where('id', $colaboradorId)->value('foto_ruta');
        DB::table('colaboradores')->where('id', $colaboradorId)->update(['foto_ruta' => $ruta, 'updated_at' => now()]);

        if ($anterior !== null && $anterior !== $ruta) {
            Storage::disk(self::DISCO_FOTOS)->delete($anterior);
        }
    }

    public function quitarFoto(int $colaboradorId): void
    {
        $anterior = DB::table('colaboradores')->where('id', $colaboradorId)->value('foto_ruta');
        DB::table('colaboradores')->where('id', $colaboradorId)->update(['foto_ruta' => null, 'updated_at' => now()]);

        if ($anterior !== null) {
            Storage::disk(self::DISCO_FOTOS)->delete($anterior);
        }
    }

    /** @return array{nombre: string, sexo: string, foto: ?string} lo que dibuja `personal::avatar` */
    public static function avatar(object|array $c): array
    {
        $c = (object) $c;
        $foto = $c->foto_ruta ?? null;

        return ['nombre' => (string) $c->nombres, 'sexo' => ($c->sexo ?? null) === 'mujer' ? 'mujer' : 'hombre',
            'foto' => $foto === null ? null : '/personal/fotos/'.$c->id.'?v='.substr(hash('sha256', (string) $foto), 0, 10)];
    }

    /** @return list<CarbonImmutable> el lunes de esta semana y las tres siguientes */
    public function semanasDelTablero(): array
    {
        $lunes = CarbonImmutable::parse(Calendario::hoy())->startOfWeek();

        return [$lunes, $lunes->addWeek(), $lunes->addWeeks(2), $lunes->addWeeks(3)];
    }

    /**
     * Personal asignado (MV-067): una tarjeta por persona activa con sus cuatro semanas; cada semana, su ocupación (la
     * suma de los porcentajes de sus asignaciones vigentes esa semana) y en qué proyectos.
     *
     * @return list<array<string, mixed>>
     */
    public function tableroAsignacion(string $buscar = ''): array
    {
        $hoy = CarbonImmutable::parse(Calendario::hoy());
        $buscar = mb_strtolower(trim($buscar));
        $tarjetas = [];

        foreach (DB::table('colaboradores as c')->join('categorias_personal as k', 'k.id', '=', 'c.categoria_id')->where('c.es_activo', true)->orderBy('c.nombres')->get(['c.*', 'k.nombre as categoria']) as $c) {
            if ($buscar !== '' && ! str_contains(mb_strtolower($c->nombres), $buscar)) {
                continue;
            }

            $habil = $c->habilitado_hasta === null ? null : CarbonImmutable::parse($c->habilitado_hasta);
            $semanas = [];

            foreach ($this->semanasDelTablero() as $lunes) {
                $partes = DB::table('asignaciones as a')->join('proyectos as p', 'p.id', '=', 'a.proyecto_id')->where('a.colaborador_id', $c->id)
                    ->where('a.desde', '<=', $lunes->addDays(6)->toDateString())->where(fn ($q) => $q->whereNull('a.hasta')->orWhere('a.hasta', '>=', $lunes->toDateString()))
                    ->orderByDesc('a.porcentaje')->get(['p.codigo', 'p.nombre', 'a.porcentaje', 'a.desde', 'a.hasta']);
                $total = (int) $partes->sum('porcentaje');
                $semanas[] = ['etiqueta' => 'Sem '.$lunes->isoWeek().' · '.$lunes->format('d/m'), 'total' => $total,
                    'tono' => $total === 0 ? 'libre' : ($total > 100 ? 'sobre' : 'normal'),
                    'proyectos' => $partes->map(fn ($p): array => ['codigo' => $p->codigo, 'nombre' => $p->nombre, 'porcentaje' => (int) $p->porcentaje,
                        'vigencia' => 'desde '.CarbonImmutable::parse($p->desde)->format('d/m').($p->hasta ? ' hasta '.CarbonImmutable::parse($p->hasta)->format('d/m') : '')])->all()];
            }

            $tarjetas[] = ['id' => (int) $c->id, 'nombres' => $c->nombres, 'categoria' => $c->categoria, 'vinculo' => $c->vinculo === 'planilla' ? 'Planilla' : 'Eventual',
                'sctr' => $habil === null ? 'Sin SCTR' : 'SCTR hasta '.$habil->format('d/m/Y'), 'sctr_mal' => $habil === null || $habil->lt($hoy),
                'avatar' => self::avatar($c), 'jornada' => TareoService::textoJornada(TareoService::jornadaDe($c)), 'semanas' => $semanas];
        }

        return $tarjetas;
    }

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
        $t = Tareo::query()->firstOrCreate(['proyecto_id' => $proyectoId, 'semana' => $lunes->startOfWeek()->toDateString()], ['estado' => 'abierto']);

        // MV-069: una semana nueva ya trae a la gente asignada con las horas de su jornada.
        if ($t->wasRecentlyCreated) {
            app(TareoService::class)->rellenar($t->id);
        }

        return $t;
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

        return TareoLinea::query()->updateOrCreate(['tareo_id' => $t->id, 'colaborador_id' => $c->id, 'partida_control_id' => $partidaId], [...$fila, 'de_jornada' => false]);
    }

    /** Cierra la semana: cada línea pasa a comprometido y ejecutado de su partida a la vez. */
    public function cerrar(int $tareoId, User $quien): Tareo
    {
        return DB::transaction(function () use ($tareoId, $quien): Tareo {
            $t = Tareo::query()->lockForUpdate()->findOrFail($tareoId);

            if ($t->estado !== 'abierto') {
                throw new RuntimeException('La semana ya está cerrada.');
            }

            // MV-069: las líneas sin horas se van; una con horas y sin partida no deja cerrar.
            foreach (TareoLinea::query()->where('tareo_id', $t->id)->get() as $l) {
                if (bccomp($l->horas(), '0', 1) === 0) {
                    $l->delete();
                } elseif ($l->partida_control_id === null) {
                    throw new RuntimeException('Falta la partida de una línea con horas.');
                }
            }

            $t->load('lineas');

            if ($t->estado !== 'abierto' || $t->lineas->isEmpty()) {
                throw new RuntimeException($t->estado !== 'abierto' ? 'La semana ya está cerrada.' : 'La semana no tiene horas.');
            }

            $imputaciones = app(ImputacionService::class);

            foreach ($t->lineas as $l) {
                $imputaciones->destinar($l, app(PartidasService::class)->destino($l->partida_control_id), $t->semana->addDays(5), 'PEN');
                $imputaciones->mover($l, MomentoImputacion::Comprometido, $l->costo());
                $imputaciones->mover($l, MomentoImputacion::Ejecutado, $l->costo());
            }

            TareoLinea::query()->where('tareo_id', $t->id)->update(['de_jornada' => false]);
            $t->update(['estado' => 'cerrado', 'cerrado_por' => $quien->id, 'cerrado_at' => Calendario::ahora()]);

            return $t->refresh();
        });
    }
}
