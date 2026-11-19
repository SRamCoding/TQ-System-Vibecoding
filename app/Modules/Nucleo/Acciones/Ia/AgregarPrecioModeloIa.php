<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Ia;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\IaConfiguracionService;

/**
 * Un precio nuevo de un modelo de IA por millón de tokens, en dólares, desde hoy o después (plan 1.11, D3): las
 * llamadas ya hechas no se recalculan. Un modelo propio va en 0.
 */
final class AgregarPrecioModeloIa extends Accion
{
    public function __construct(private readonly IaConfiguracionService $configuracion) {}

    public function clave(): string
    {
        return 'nucleo.ia-agregar-precio';
    }

    public function nombre(): string
    {
        return 'Agregar precio de modelo';
    }

    public function campos(): array
    {
        return [
            Campo::entero('modelo_id', 'Modelo'),
            Campo::fecha('vigente_desde', 'Vigente desde'),
            Campo::decimal('usd_millon_entrada', 'USD por millón de tokens de entrada', escala: 6)->conReglas('min:0'),
            Campo::decimal('usd_millon_salida', 'USD por millón de tokens de salida', escala: 6)->conReglas('min:0'),
        ];
    }

    public function permiso(): string
    {
        return 'ia.configurar';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        return $this->configuracion->agregarPrecio($datos['modelo_id'], $datos['vigente_desde']->toDateString(), $datos['usd_millon_entrada'], $datos['usd_millon_salida'])->id;
    }
}
