<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Ia;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\IaConfiguracionService;

/**
 * Gerencia autoriza lo que un proceso de IA envía fuera de la empresa, y adónde, y fija su tope del mes en dólares
 * (plan 1.11, D2 y D3). Si después cambia lo que envía o su destino, la autorización cae sola.
 */
final class AutorizarProcesoIa extends Accion
{
    public function __construct(private readonly IaConfiguracionService $configuracion) {}

    public function clave(): string
    {
        return 'nucleo.ia-autorizar-proceso';
    }

    public function nombre(): string
    {
        return 'Autorizar proceso de IA';
    }

    public function campos(): array
    {
        return [
            Campo::entero('proceso_id', 'Proceso'),
            Campo::decimal('tope_mensual_usd', 'Tope del mes (USD)')->conReglas('min:0'),
            Campo::entero('version', 'Versión')->conReglas('min:1')->deControl(),
        ];
    }

    public function permiso(): string
    {
        return 'ia.autorizar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->configuracion->autorizar($datos['proceso_id'], $datos['tope_mensual_usd'], $datos['version']);

        return null;
    }
}
