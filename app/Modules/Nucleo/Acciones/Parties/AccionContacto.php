<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Parties\DatosContacto;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\ContactosPartyService;

/**
 * Base de las acciones sobre los contactos de una party (plan 1.3b, D1, D2 y D8). Clave
 * `nucleo.parties.<operacion>`, permiso `parties.gestionar-contactos`. Las reglas están en ContactosPartyService;
 * otro módulo (el CRM) escribe contactos solo por estas acciones (D7).
 */
abstract class AccionContacto extends Accion
{
    public function __construct(protected readonly ContactosPartyService $contactos) {}

    abstract protected function operacion(): string;

    public function clave(): string
    {
        return 'nucleo.parties.'.$this->operacion();
    }

    public function permiso(): string
    {
        return 'parties.gestionar-contactos';
    }

    protected function campoContacto(): Campo
    {
        return Campo::entero('contacto_id', 'Contacto')->conReglas('exists:party_contactos,id');
    }

    protected function campoVersion(): Campo
    {
        return Campo::entero('version', 'Versión')->conReglas('min:1')->deControl();
    }

    /**
     * Los datos de la persona, como están en el formulario.
     *
     * @return list<Campo>
     */
    public static function camposDeDatos(): array
    {
        return [
            Campo::texto('nombres', 'Nombres', maximo: 100),
            Campo::texto('apellidos', 'Apellidos', maximo: 100),
            Campo::texto('cargo', 'Cargo', maximo: 120)->opcional(),
            Campo::texto('area', 'Área', maximo: 120)->opcional(),
            Campo::texto('telefono', 'Teléfono', maximo: 30)->opcional(),
            Campo::texto('email', 'Correo', maximo: 150)->opcional(),
            Campo::booleano('es_decisor', 'Decide la compra')->opcional(),
            Campo::fecha('desde', 'Desde (si se sabe)')->opcional(),
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected static function datos(array $datos, bool $esPrincipal = false): DatosContacto
    {
        return new DatosContacto(
            $datos['nombres'],
            $datos['apellidos'],
            cargo: $datos['cargo'] ?? null,
            area: $datos['area'] ?? null,
            telefono: $datos['telefono'] ?? null,
            email: $datos['email'] ?? null,
            esDecisor: (bool) ($datos['es_decisor'] ?? false),
            esPrincipal: $esPrincipal,
            desde: $datos['desde'] ?? null,
            hasta: $datos['hasta'] ?? null,
        );
    }
}
