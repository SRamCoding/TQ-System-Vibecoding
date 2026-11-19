<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;

/**
 * Cada persona registra su cumpleaños, sin año, para su saludo (plan 1.4b, D6). Sin permiso: es su propio dato.
 * Los dos vacíos lo borran. Desde «Mis datos» va con la versión de la cuenta leída al abrir (plan 1.4h, D4): si alguien
 * la cambió mientras tanto, el aviso de siempre; sin versión, la última escritura gana, como antes.
 */
final class RegistrarMiCumpleanos extends Accion
{
    public function __construct(private readonly PreferenciasUsuarioService $preferencias) {}

    public function clave(): string
    {
        return 'nucleo.registrar-mi-cumpleanos';
    }

    public function nombre(): string
    {
        return 'Registrar mi cumpleaños';
    }

    public function campos(): array
    {
        return [...self::camposDeCumpleanos(), Campo::entero('version', 'Versión')->opcional()->conReglas('min:1')->deControl()];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->preferencias->registrarCumpleanos($usuario->id, $datos['cumpleanos_dia'], $datos['cumpleanos_mes'], $datos['version'] ?? null);

        return null;
    }

    /**
     * Día y mes, que se pueden vaciar: deben venir aunque sea vacíos (`present`), para que omitirlos no borre el dato.
     *
     * @return list<Campo>
     */
    public static function camposDeCumpleanos(): array
    {
        return [
            Campo::entero('cumpleanos_dia', 'Día')->opcional()->conReglas('present', 'min:1', 'max:31'),
            Campo::entero('cumpleanos_mes', 'Mes (1 a 12)')->opcional()->conReglas('present', 'min:1', 'max:12'),
        ];
    }
}
