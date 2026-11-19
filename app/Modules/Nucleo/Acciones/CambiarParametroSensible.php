<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\Services\ParametrosService;

/**
 * Cambia un parámetro que decide a dónde va el dinero (`DefinicionParametro::$esSensible`), con su propio permiso:
 * como los campos de dinero de los catálogos (plan 1.2b, E6). El primero, el RUC del banco de detracciones
 * (plan 1.3b). Mismos campos que «Cambiar parámetro»; solo acepta parámetros sensibles.
 */
final class CambiarParametroSensible extends Accion
{
    public function __construct(private readonly ParametrosService $parametros) {}

    public function clave(): string
    {
        return 'nucleo.cambiar-parametro-sensible';
    }

    public function nombre(): string
    {
        return 'Cambiar parámetro de dinero';
    }

    public function campos(): array
    {
        return app(CambiarParametro::class)->campos();
    }

    public function permiso(): string
    {
        return 'parametros.editar-sensibles';
    }

    public function ejecutar(array $datos, User $usuario): mixed
    {
        $this->parametros->cambiarDesdeTexto($datos['parametro'], $datos['valor'], $datos['version'], $datos['descripcion'] ?? null, deDinero: true);

        return null;
    }
}
