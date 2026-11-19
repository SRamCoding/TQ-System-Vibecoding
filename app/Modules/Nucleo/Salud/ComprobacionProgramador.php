<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Salud;

use App\Modules\Nucleo\Console\LatidoProgramador;
use App\Modules\Nucleo\Services\SaludService;
use App\Modules\Nucleo\Tiempo\Calendario;

/**
 * ¿Corre el programador? (plan 1.12, D7). El latido llega cada 5 minutos: más de 15 sin él es una falla, porque sin
 * programador no hay tipo de cambio, ni alertas, ni respaldos.
 */
final readonly class ComprobacionProgramador implements ComprobacionSalud
{
    public const int MINUTOS_SIN_LATIDO = 15;

    public function __construct(private SaludService $salud) {}

    public function clave(): string
    {
        return 'nucleo.programador';
    }

    public function titulo(): string
    {
        return 'Programador';
    }

    public function icono(): string
    {
        return 'heroicon-o-signal';
    }

    public function revisar(): ResultadoComprobacion
    {
        $ultimo = $this->salud->ultima(LatidoProgramador::TAREA);

        if ($ultimo === null) {
            return new ResultadoComprobacion(EstadoSalud::SinDatos, 'Todavía no llegó ningún latido.');
        }

        $detalle = [
            'Último latido' => $ultimo->iniciada_at->timezone('America/Lima')->format('d/m/Y H:i'),
            'Healthchecks' => ($ultimo->datos['enviado'] ?? false) ? 'avisado' : 'sin configurar o sin respuesta',
        ];

        if ($ultimo->iniciada_at->lt(Calendario::ahora()->subMinutes(self::MINUTOS_SIN_LATIDO))) {
            return new ResultadoComprobacion(EstadoSalud::Falla, 'El programador no corre desde '.$ultimo->iniciada_at->diffForHumans().'.', $detalle);
        }

        return new ResultadoComprobacion(EstadoSalud::Bien, 'El programador corre con normalidad.', $detalle);
    }
}
