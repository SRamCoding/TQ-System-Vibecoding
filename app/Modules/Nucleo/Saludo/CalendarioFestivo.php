<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\Enums\Ocasion;
use App\Modules\Nucleo\Enums\Temporada;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Qué fecha especial es un día (plan 1.4b, D5). Cada fecha del calendario tiene su ventana de días, porque casi todas
 * caen en domingo o feriado, cuando nadie entra:
 *
 * | Ocasión         | Ventana                                                        |
 * |-----------------|----------------------------------------------------------------|
 * | Año Nuevo       | 26 dic – 2 ene                                                 |
 * | Semana Santa    | Domingo de Ramos – Domingo de Resurrección                     |
 * | Día de la Madre | lunes a domingo de la semana del 2.º domingo de mayo           |
 * | Día del Padre   | lunes a domingo de la semana del 3.er domingo de junio         |
 * | Fiestas Patrias | 21 – 29 jul                                                    |
 * | Navidad         | 15 – 25 dic                                                    |
 *
 * El cumpleaños y el aniversario valen su día; si cae en sábado o domingo, también el viernes anterior y el lunes
 * siguiente. Un 29 de febrero se celebra el 28 en los años no bisiestos. Si coinciden, gana lo más personal:
 * cumpleaños, aniversario y después el calendario (cuyas ventanas no se pisan). Los demás días no hay ocasión: la
 * ambientación usa la temporada.
 */
final readonly class CalendarioFestivo
{
    public function __construct(private ParametrosService $parametros) {}

    public function ocasion(CarbonImmutable $fecha, ?int $cumpleanosDia = null, ?int $cumpleanosMes = null): ?Ocasion
    {
        $fecha = self::dia($fecha);

        if ($cumpleanosDia !== null && $cumpleanosMes !== null && self::anioCelebrado($fecha, $cumpleanosDia, $cumpleanosMes) !== null) {
            return Ocasion::Cumpleanos;
        }

        if ($this->aniosDelAniversario($fecha) !== null) {
            return Ocasion::Aniversario;
        }

        return self::delCalendario($fecha);
    }

    /**
     * Si el día está en la ventana del aniversario, cuántos años cumple Techquk (D10); si no, o sin fecha de fundación,
     * nulo. Cuenta por el año del aniversario que se celebra: el viernes 31 de diciembre celebra el del sábado 1 de enero.
     */
    public function aniosDelAniversario(CarbonImmutable $fecha): ?int
    {
        $fundacion = ValidadorAniversario::fecha($this->parametros->texto('interfaz.aniversario_techquk'));
        $anio = $fundacion === null ? null : self::anioCelebrado(self::dia($fecha), $fundacion['dia'], $fundacion['mes']);

        return $anio === null || $anio <= $fundacion['anio'] ? null : $anio - $fundacion['anio'];
    }

    public function temporada(CarbonImmutable $fecha): Temporada
    {
        return Temporada::deLaFecha($fecha);
    }

    /** La fecha del calendario cuya ventana incluye el día, o nula. */
    public static function delCalendario(CarbonImmutable $fecha): ?Ocasion
    {
        $fecha = self::dia($fecha);
        $anio = $fecha->year;
        $mesDia = $fecha->format('m-d');
        $resurreccion = self::domingoDeResurreccion($anio);
        $madre = self::domingoDelMes($anio, 5, 2);
        $padre = self::domingoDelMes($anio, 6, 3);

        return match (true) {
            $mesDia >= '12-26' || $mesDia <= '01-02' => Ocasion::AnioNuevo,
            $mesDia >= '12-15' && $mesDia <= '12-25' => Ocasion::Navidad,
            $mesDia >= '07-21' && $mesDia <= '07-29' => Ocasion::FiestasPatrias,
            $fecha->between($resurreccion->subDays(7), $resurreccion) => Ocasion::SemanaSanta,
            $fecha->between($madre->subDays(6), $madre) => Ocasion::DiaMadre,
            $fecha->between($padre->subDays(6), $padre) => Ocasion::DiaPadre,
            default => null,
        };
    }

    /** El día civil de la fecha, a medianoche de Lima: así se comparan días y no instantes. */
    private static function dia(CarbonImmutable $fecha): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $fecha->toDateString(), Calendario::ZONA)
            ?: throw new LogicException('Fecha inválida.');
    }

    /** Domingo de Resurrección del calendario gregoriano (algoritmo de Meeus, Jones y Butcher). */
    public static function domingoDeResurreccion(int $anio): CarbonImmutable
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $n = $h + $l - 7 * $m + 114;

        return CarbonImmutable::create($anio, intdiv($n, 31), ($n % 31) + 1, 0, 0, 0, Calendario::ZONA)
            ?? throw new LogicException("No se pudo calcular la Pascua de {$anio}.");
    }

    /** El enésimo domingo de un mes. */
    public static function domingoDelMes(int $anio, int $mes, int $enesimo): CarbonImmutable
    {
        $primero = CarbonImmutable::create($anio, $mes, 1, 0, 0, 0, Calendario::ZONA) ?? throw new LogicException("Mes inválido: {$anio}-{$mes}.");

        return $primero->addDays((7 - $primero->dayOfWeek) % 7)->addWeeks($enesimo - 1);
    }

    /**
     * El año de la fecha personal (cumpleaños, aniversario) que el día celebra, o nulo: ese día, y si cae en fin de
     * semana también el viernes anterior y el lunes siguiente.
     */
    private static function anioCelebrado(CarbonImmutable $fecha, int $dia, int $mes): ?int
    {
        foreach ([$fecha->year - 1, $fecha->year, $fecha->year + 1] as $anio) {
            $celebrado = self::enElAnio($anio, $dia, $mes);

            if ($celebrado->equalTo($fecha)
                || ($celebrado->isWeekend() && ($fecha->equalTo($celebrado->previous(CarbonImmutable::FRIDAY)) || $fecha->equalTo($celebrado->next(CarbonImmutable::MONDAY))))) {
                return $anio;
            }
        }

        return null;
    }

    /** El día en que se celebra en ese año: el 29 de febrero pasa al 28 en los no bisiestos. */
    private static function enElAnio(int $anio, int $dia, int $mes): CarbonImmutable
    {
        if ($mes === 2 && $dia === 29 && ! checkdate(2, 29, $anio)) {
            $dia = 28;
        }

        return CarbonImmutable::create($anio, $mes, $dia, 0, 0, 0, Calendario::ZONA) ?? throw new LogicException("Fecha inválida: {$anio}-{$mes}-{$dia}.");
    }
}
