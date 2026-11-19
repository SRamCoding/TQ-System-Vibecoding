<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Tiempo;

use App\Modules\Nucleo\Enums\FueraDeHorario;
use App\Modules\Nucleo\Services\ParametrosService;
use Carbon\CarbonImmutable;

/**
 * El horario de la empresa (plan 1.4e, D2), de los parámetros: de `interfaz.horario_inicio` a `interfaz.horario_fin`,
 * los días de `interfaz.horario_dias`. Fuera de horario: un feriado o día libre del catálogo (aunque caiga en fin de
 * semana laborable), un día no laborable (fin de semana), de madrugada o desde la hora de fin (noche: a la 1:00 se
 * sigue «por aquí», no se «madrugó») o antes de la hora de inicio (temprano).
 */
final readonly class HorarioEmpresa
{
    /** Hasta esta hora, la madrugada cuenta como noche (el mismo corte de `MomentoDia`). */
    private const string FIN_DE_LA_MADRUGADA = '05:00';

    public function __construct(
        private ParametrosService $parametros,
        private CalendarioLaboral $calendario,
    ) {}

    /** @param  CarbonImmutable  $ahora  en hora de Lima; null: en horario */
    public function fueraDeHorario(CarbonImmutable $ahora): ?FueraDeHorario
    {
        $ahora = $ahora->setTimezone(Calendario::ZONA);
        $dias = array_map('intval', explode(',', $this->parametros->texto('interfaz.horario_dias')));

        if (! in_array($ahora->dayOfWeekIso, $dias, true)) {
            return FueraDeHorario::FinDeSemana;
        }

        if ($this->calendario->noTrabaja($ahora)) {
            return FueraDeHorario::Feriado;
        }

        $hora = $ahora->format('H:i');

        return match (true) {
            $hora < self::FIN_DE_LA_MADRUGADA => FueraDeHorario::Noche,
            $hora < $this->parametros->texto('interfaz.horario_inicio') => FueraDeHorario::Temprano,
            $hora >= $this->parametros->texto('interfaz.horario_fin') => FueraDeHorario::Noche,
            default => null,
        };
    }
}
