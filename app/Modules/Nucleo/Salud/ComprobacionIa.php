<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Salud;

use App\Modules\Nucleo\Ia\RegistroProcesosIa;
use App\Modules\Nucleo\Models\IaProceso;
use App\Modules\Nucleo\Services\IaService;

/**
 * ¿Cuánto gasta la IA frente a su tope? (plan 1.12; `monitoreo.md`: uso de cada proceso de IA frente a su tope). Por cada
 * proceso encendido: lo gastado en el mes y su tope. Al 80 % avisa; al llegar al tope, el proceso se apaga hasta el mes
 * siguiente (plan 1.11, D5).
 */
final readonly class ComprobacionIa implements ComprobacionSalud
{
    public const int PORCENTAJE_AVISO = 80;

    public function __construct(
        private IaService $ia,
        private RegistroProcesosIa $procesos,
    ) {}

    public function clave(): string
    {
        return 'nucleo.ia';
    }

    public function titulo(): string
    {
        return 'Gasto de IA';
    }

    public function icono(): string
    {
        return 'heroicon-o-chart-bar-square';
    }

    public function revisar(): ResultadoComprobacion
    {
        $encendidos = IaProceso::query()->where('esta_activo', true)->orderBy('codigo')->get();

        if ($encendidos->isEmpty()) {
            return new ResultadoComprobacion(EstadoSalud::SinDatos, 'No hay procesos de IA encendidos.');
        }

        $definiciones = $this->procesos->procesos();
        $detalle = [];
        $peor = EstadoSalud::Bien;

        foreach ($encendidos as $proceso) {
            $gasto = $this->ia->gastoDelMes($proceso);
            $tope = (string) $proceso->tope_mensual_usd;
            $detalle[$definiciones[$proceso->codigo]->nombre ?? $proceso->codigo] = 'US$ '.self::monto($gasto).' de '.self::monto($tope).' ('.self::porcentaje($gasto, $tope).' %)';
            $estado = self::estado($gasto, $tope);

            if ($estado === EstadoSalud::Falla || ($estado === EstadoSalud::Aviso && $peor === EstadoSalud::Bien)) {
                $peor = $estado;
            }
        }

        return new ResultadoComprobacion($peor, match ($peor) {
            EstadoSalud::Falla => 'Un proceso llegó a su tope: está apagado hasta el mes siguiente.',
            EstadoSalud::Aviso => 'Un proceso pasó el '.self::PORCENTAJE_AVISO.' % de su tope del mes.',
            default => 'Todos los procesos están dentro de su tope.',
        }, $detalle);
    }

    /** Lo gastado como porcentaje entero del tope (sin tope, 100: no puede gastar nada). */
    public static function porcentaje(string $gasto, string $tope): int
    {
        return bccomp($tope, '0', 6) > 0 ? (int) bcdiv(bcmul($gasto, '100', 6), $tope, 0) : 100;
    }

    /** Bien por debajo del 80 %; atender desde el 80 %; falla al llegar al tope (el proceso se apaga). */
    public static function estado(string $gasto, string $tope): EstadoSalud
    {
        $porcentaje = self::porcentaje($gasto, $tope);

        return match (true) {
            $porcentaje >= 100 => EstadoSalud::Falla,
            $porcentaje >= self::PORCENTAJE_AVISO => EstadoSalud::Aviso,
            default => EstadoSalud::Bien,
        };
    }

    /** Con separador de miles y dos decimales, sin pasar por `float` (regla 4): `bcmath` redondea el texto. */
    public static function monto(string $valor): string
    {
        [$entero, $decimales] = explode('.', bcadd($valor, '0.005', 2));

        return number_format((int) $entero).'.'.$decimales;
    }
}
