<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\TipoPersona;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Parties\PartyRegistrada;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Registra una persona o empresa, con sus roles iniciales desde hoy en la misma operación. Devuelve su id y,
 * si existe, la party del otro documento de la misma persona (DNI ↔ RUC 10): un aviso, no un bloqueo (D9).
 * Sin marcas tributarias (D7). Con roles, exige además el permiso de asignarlos.
 */
final class RegistrarParty extends AccionParty
{
    public const string PERMISO_ROLES = 'parties.asignar-roles';

    protected function operacion(): string
    {
        return 'registrar';
    }

    public function nombre(): string
    {
        return 'Registrar persona o empresa';
    }

    public function campos(): array
    {
        return [
            Campo::elementoDe('tipo_documento_id', 'Tipo de documento', Catalogo::TiposDocumentoIdentidad),
            // Más largo que el número guardado: puede llegar con espacios, guiones o puntos.
            Campo::texto('numero_documento', 'Número de documento', maximo: 30),
            Campo::opcion('tipo', 'Tipo de persona (solo si el documento no lo dice)', TipoPersona::class)->opcional(),
            ...self::camposDeDatos(),
            Campo::listaDe('roles', 'Roles iniciales (desde hoy)', Catalogo::RolesParty)->opcional(),
        ];
    }

    public function permiso(): string
    {
        return 'parties.crear';
    }

    public function ejecutar(array $datos, User $usuario): PartyRegistrada
    {
        // El ejecutor ya revisó `parties.crear`; los roles piden también el permiso de asignarlos.
        if ($datos['roles'] !== [] && ! $usuario->can(self::PERMISO_ROLES)) {
            throw AccionRechazada::sinPermiso('Asignar rol');
        }

        $roles = array_map(fn (int $id): string => $this->catalogos->codigoDe(Catalogo::RolesParty, $id), $datos['roles']);

        return $this->parties->registrar(new DatosParty(
            codigoTipoDocumento: $this->catalogos->codigoDe(Catalogo::TiposDocumentoIdentidad, $datos['tipo_documento_id']),
            numeroDocumento: $datos['numero_documento'],
            tipo: $datos['tipo'],
            razonSocial: $datos['razon_social'],
            nombreComercial: $datos['nombre_comercial'],
            nombres: $datos['nombres'],
            apellidos: $datos['apellidos'],
            direccionFiscal: $datos['direccion_fiscal'],
            ubigeoId: $datos['ubigeo_id'],
            telefono: $datos['telefono'],
            email: $datos['email'],
        ), $roles);
    }

    /**
     * Los datos que se escriben al registrar y se cambian al editar. Nombres y apellidos son de una persona
     * natural; la razón social, de una jurídica (el servicio rechaza el que no corresponde).
     *
     * @return list<Campo>
     */
    public static function camposDeDatos(): array
    {
        return [
            Campo::texto('razon_social', 'Razón social (persona jurídica)', maximo: 200)->opcional(),
            Campo::texto('nombres', 'Nombres (persona natural)', maximo: 100)->opcional(),
            Campo::texto('apellidos', 'Apellidos (persona natural)', maximo: 100)->opcional(),
            Campo::texto('nombre_comercial', 'Nombre comercial', maximo: 200)->opcional(),
            Campo::texto('direccion_fiscal', 'Dirección fiscal', maximo: 255)->opcional(),
            Campo::elementoDe('ubigeo_id', 'Ubigeo', Catalogo::Ubigeos)->opcional(),
            Campo::texto('telefono', 'Teléfono', maximo: 30)->opcional(),
            Campo::texto('email', 'Correo', maximo: 150)->opcional(),
        ];
    }
}
