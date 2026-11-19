<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Tiempo;

use App\Modules\Nucleo\Models\Feriado;
use Carbon\CarbonImmutable;

/**
 * Qué días trabaja Techquk (plan 1.4d, D2): de lunes a viernes, salvo los días del catálogo de feriados que la empresa
 * no trabaja (un feriado nacional o un día libre de la empresa, siempre; un día no laborable del gobierno, solo si está
 * marcado). Aparte de CalendarioFestivo, que dice qué se celebra; este sirve también para plazos, tareo o planilla.
 * Un año sin feriados cargados solo descuenta los fines de semana.
 */
final class CalendarioLaboral
{
    /** @var array<int, array<string, bool>> los días no trabajados de cada año, leídos una vez por petición */
    private array $noTrabajados = [];

    public function esHabil(CarbonImmutable $fecha): bool
    {
        $dia = $fecha->setTimezone(Calendario::ZONA)->startOfDay();

        return ! $dia->isWeekend() && ! isset($this->noTrabajadosDe($dia->year)[$dia->toDateString()]);
    }

    /** Si ese día Techquk no trabaja por el catálogo (feriado o día libre), sea el día de la semana que sea. */
    public function noTrabaja(CarbonImmutable $fecha): bool
    {
        $dia = $fecha->setTimezone(Calendario::ZONA)->startOfDay();

        return isset($this->noTrabajadosDe($dia->year)[$dia->toDateString()]);
    }

    /** El mismo día si es hábil; si no, el siguiente que lo sea. */
    public function primerHabilDesde(CarbonImmutable $fecha): CarbonImmutable
    {
        $dia = $fecha->setTimezone(Calendario::ZONA)->startOfDay();

        // Un año entero sin un día hábil no existe; el tope solo evita un bucle sin fin con datos absurdos.
        for ($paso = 0; $paso < 366 && ! $this->esHabil($dia); $paso++) {
            $dia = $dia->addDay();
        }

        return $dia;
    }

    /** @return array<string, bool> */
    private function noTrabajadosDe(int $anio): array
    {
        return $this->noTrabajados[$anio] ??= Feriado::query()
            ->where('es_activo', true)
            ->where('techquk_no_trabaja', true)
            ->whereBetween('codigo', ["{$anio}-01-01", "{$anio}-12-31"])
            ->pluck('codigo')
            ->mapWithKeys(fn (mixed $codigo): array => [(string) $codigo => true])
            ->all();
    }
}
