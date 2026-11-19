<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Seguridad;

use App\Modules\Nucleo\Excepciones\SeguridadRechazada;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Seguridad\DatosControl;
use App\Modules\Nucleo\Services\SeguridadService;
use Carbon\CarbonImmutable;

/**
 * Base de las acciones de seguridad (plan 1.4, D4 y D7-D10). Clave `nucleo.seguridad.<operacion>`. Permiso
 * `usuarios.gestionar` para cuentas y roles de una persona; `roles.gestionar` para los roles, sus permisos y la
 * separación de funciones. Las reglas están en SeguridadService.
 */
abstract class AccionSeguridad extends Accion
{
    public function __construct(protected readonly SeguridadService $seguridad) {}

    abstract protected function operacion(): string;

    public function clave(): string
    {
        return 'nucleo.seguridad.'.$this->operacion();
    }

    public function permiso(): string
    {
        return SeguridadService::PERMISO_GESTIONAR_USUARIOS;
    }

    protected static function campoUsuario(): Campo
    {
        return Campo::entero('usuario_id', 'Usuario')->conReglas('exists:users,id');
    }

    protected static function campoRol(): Campo
    {
        return Campo::entero('rol_id', 'Rol')->conReglas('exists:roles,id');
    }

    protected static function campoVersion(): Campo
    {
        return Campo::entero('version', 'Versión')->conReglas('min:1')->deControl();
    }

    /**
     * El control compensatorio que se pide ahí mismo si el cambio abre un cruce de funciones (D9, D10). Opcional:
     * si no hace falta, se ignora; si hace falta y no viene, el servicio dice qué cruces lo piden.
     *
     * @return list<Campo>
     */
    protected static function camposDeControl(bool $obligatorio = false): array
    {
        $campos = [
            Campo::texto('control', 'Control compensatorio', maximo: 2000),
            Campo::entero('responsable_user_id', 'Responsable del control')->conReglas('exists:users,id'),
            Campo::fecha('control_vigente_hasta', 'Control vigente hasta')->opcional(),
        ];

        return $obligatorio ? $campos : [$campos[0]->opcional(), $campos[1]->opcional(), $campos[2]];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected static function datosControl(array $datos): ?DatosControl
    {
        $control = trim((string) ($datos['control'] ?? ''));
        $responsable = $datos['responsable_user_id'] ?? null;

        if ($control === '' && $responsable === null) {
            return null;
        }

        if ($control === '' || $responsable === null) {
            throw SeguridadRechazada::controlIncompleto();
        }

        /** @var ?CarbonImmutable $hasta */
        $hasta = $datos['control_vigente_hasta'] ?? null;

        return new DatosControl($control, (int) $responsable, $hasta?->toDateString());
    }
}
