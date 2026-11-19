<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Ia;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\IaService;

/**
 * Rechazar una sugerencia de la IA: no se usó y no se creó nada con ella (plan 1.11, D9). Aceptarla o corregirla no
 * pasa por aquí: lo hace la acción del módulo que crea el registro, en su misma transacción (ajuste del desarrollador
 * del 2026-10-28). El permiso es el que declara el proceso de la sugerencia, no uno global: no hay uno fijo que exigir
 * aquí (`permiso()` queda en null) y lo revisa `IaService::resolver()`.
 */
final class ResolverSugerenciaIa extends Accion
{
    public function __construct(private readonly IaService $ia) {}

    public function clave(): string
    {
        return 'nucleo.ia-rechazar-sugerencia';
    }

    public function nombre(): string
    {
        return 'Rechazar sugerencia de IA';
    }

    public function campos(): array
    {
        return [
            Campo::entero('sugerencia_id', 'Sugerencia'),
            Campo::entero('version', 'Versión')->conReglas('min:1')->deControl(),
        ];
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->ia->rechazar($datos['sugerencia_id'], $datos['version'])->id;
    }
}
