<?php

declare(strict_types=1);

namespace App\Modules\Crm\Actividades\Services;

use App\Modules\Crm\Actividades\Models\Actividad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * MVP rápido (ficha t1-agenda): registrar una gestión y pedir la siguiente (v4: «la próxima acción es el mecanismo
 * central del CRM, con la opción explícita de no programar ninguna»). Registrar una actividad de una oportunidad
 * cierra la próxima acción pendiente de esa oportunidad (se cumplió) y deja la nueva.
 */
final readonly class ActividadesService
{
    public const string PERMISO_VER = 'crm.actividades.ver';

    public const string PERMISO_REGISTRAR = 'crm.actividades.registrar';

    /** Los atajos de la próxima acción, en días. */
    public const array PROXIMA = ['manana' => 1, 'tres_dias' => 3, 'semana' => 7];

    /**
     * @param  ?string  $proxima  'manana', 'tres_dias', 'semana' o null (ninguna)
     */
    public function registrar(string $tipo, int $cuentaId, ?int $oportunidadId, string $asunto, CarbonImmutable $fecha, ?string $resultado, ?string $proxima, ?string $asuntoProxima, User $quien): Actividad
    {
        if (! isset(Actividad::TIPOS[$tipo])) {
            throw new RuntimeException('Elige el tipo: visita, llamada, correo o reunión.');
        }

        if (trim($asunto) === '') {
            throw new RuntimeException('Escribe el asunto.');
        }

        if ($proxima !== null && ! isset(self::PROXIMA[$proxima])) {
            throw new RuntimeException('La próxima acción es mañana, en 3 días, en una semana o ninguna.');
        }

        return DB::transaction(function () use ($tipo, $cuentaId, $oportunidadId, $asunto, $fecha, $resultado, $proxima, $asuntoProxima, $quien): Actividad {
            $realizada = $fecha->lte(Calendario::ahora());

            // Al registrar lo hecho, la próxima acción pendiente de la misma oportunidad (o cuenta) queda cumplida.
            if ($realizada) {
                Actividad::query()->where('es_realizada', false)->where('cuenta_id', $cuentaId)
                    ->where(fn ($q) => $oportunidadId === null ? $q->whereNull('oportunidad_id') : $q->where('oportunidad_id', $oportunidadId))
                    ->where('fecha', '<=', Calendario::ahora()->endOfDay())
                    ->update(['es_realizada' => true, 'resultado' => DB::raw("COALESCE(resultado, 'Cumplida al registrar la gestión siguiente.')")]);
            }

            $actividad = Actividad::query()->create([
                'tipo' => $tipo, 'cuenta_id' => $cuentaId, 'oportunidad_id' => $oportunidadId, 'asunto' => trim($asunto),
                'fecha' => $fecha, 'resultado' => $resultado === null || trim($resultado) === '' ? null : trim($resultado),
                'es_realizada' => $realizada, 'responsable_user_id' => $quien->id,
            ]);

            if ($proxima !== null) {
                Actividad::query()->create([
                    'tipo' => $tipo === 'correo' ? 'llamada' : $tipo, 'cuenta_id' => $cuentaId, 'oportunidad_id' => $oportunidadId,
                    'asunto' => trim((string) $asuntoProxima) !== '' ? trim((string) $asuntoProxima) : "Seguimiento: {$actividad->asunto}",
                    'fecha' => CarbonImmutable::parse(Calendario::hoy(), 'America/Lima')->addDays(self::PROXIMA[$proxima])->setTime(10, 0),
                    'es_realizada' => false, 'responsable_user_id' => $quien->id,
                ]);
            }

            return $actividad;
        });
    }

    /**
     * MV-022: la actividad de una cuenta para la ficha de su persona o empresa: las pendientes primero y lo último hecho.
     *
     * @return list<array{tipo: string, asunto: string, fecha: CarbonImmutable, hecha: bool, resultado: ?string, quien: string, oportunidad: ?string}>
     */
    public function deCuenta(int $cuentaId, int $limite = 8): array
    {
        return Actividad::query()->where('actividades.cuenta_id', $cuentaId)
            ->leftJoin('users', 'users.id', '=', 'actividades.responsable_user_id')
            ->leftJoin('oportunidades', 'oportunidades.id', '=', 'actividades.oportunidad_id')
            ->orderBy('actividades.es_realizada')->orderByDesc('actividades.fecha')->limit($limite)
            ->get(['actividades.tipo', 'actividades.asunto', 'actividades.fecha', 'actividades.es_realizada', 'actividades.resultado', 'users.name as quien', 'oportunidades.numero as oportunidad'])
            ->map(fn (Actividad $a): array => [
                'tipo' => Actividad::TIPOS[$a->tipo] ?? $a->tipo, 'asunto' => $a->asunto, 'fecha' => CarbonImmutable::parse($a->fecha),
                'hecha' => (bool) $a->es_realizada, 'resultado' => $a->resultado, 'quien' => (string) $a->getAttribute('quien'), 'oportunidad' => $a->getAttribute('oportunidad'),
            ])->all();
    }

    /** Marca hecha una pendiente, con lo que pasó. */
    public function cumplir(int $actividadId, ?string $resultado): Actividad
    {
        $a = Actividad::query()->findOrFail($actividadId);
        $a->update(['es_realizada' => true, 'resultado' => $resultado]);

        return $a;
    }

    /**
     * La próxima acción de cada oportunidad, en lote: «Llamar a … · 21/11».
     *
     * @param  list<int>  $oportunidadIds
     * @return array<int, array{texto: string, vencida: bool}>
     */
    public function proximasDeOportunidades(array $oportunidadIds): array
    {
        if ($oportunidadIds === []) {
            return [];
        }

        $hoy = Calendario::hoy();
        $resultado = [];

        foreach (Actividad::query()->where('es_realizada', false)->whereIn('oportunidad_id', $oportunidadIds)->orderBy('fecha')->get() as $a) {
            $resultado[$a->oportunidad_id] ??= [
                'texto' => "{$a->asunto} · {$a->fecha->setTimezone('America/Lima')->format('d/m')}",
                'vencida' => $a->fecha->setTimezone('America/Lima')->toDateString() < $hoy,
            ];
        }

        return $resultado;
    }
}
