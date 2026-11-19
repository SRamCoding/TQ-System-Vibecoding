<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\GranMomentoService;
use App\Modules\Nucleo\Tiempo\Calendario;

/**
 * «Lo vi» del gran momento (plan 1.4d, D4): siempre a nombre de quien inició sesión (no recibe a la persona), solo el
 * vigente, sin fallar si se marca dos veces. Devuelve si fue la primera marca. Sin permiso: es de la propia persona.
 */
final class MarcarMomentoVisto extends Accion
{
    public function __construct(private readonly GranMomentoService $granMomento) {}

    public function clave(): string
    {
        return 'nucleo.marcar-momento-visto';
    }

    public function nombre(): string
    {
        return 'Marcar como visto el gran momento';
    }

    public function campos(): array
    {
        return [Campo::entero('gran_momento', 'Gran momento')];
    }

    /**
     * @return array{primera: bool}
     */
    public function ejecutar(array $datos, User $usuario): mixed
    {
        return ['primera' => $this->granMomento->marcarVisto($usuario, $datos['gran_momento'], Calendario::ahora())];
    }
}
