<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Parties\DatosSede;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\SedesPartyService;

/**
 * Base de las acciones sobre las sedes de una party (plan 1.3b, D3, D4 y D8). Clave `nucleo.parties.<operacion>`,
 * permiso `parties.gestionar-sedes`. Las reglas están en SedesPartyService.
 */
abstract class AccionSede extends Accion
{
    public function __construct(protected readonly SedesPartyService $sedes) {}

    abstract protected function operacion(): string;

    public function clave(): string
    {
        return 'nucleo.parties.'.$this->operacion();
    }

    public function permiso(): string
    {
        return 'parties.gestionar-sedes';
    }

    protected function campoSede(): Campo
    {
        return Campo::entero('sede_id', 'Sede')->conReglas('exists:party_sedes,id');
    }

    protected function campoVersion(): Campo
    {
        return Campo::entero('version', 'Versión')->conReglas('min:1')->deControl();
    }

    /**
     * Las coordenadas van como texto: nunca `float` (regla 4). El servicio revisa forma y rango.
     *
     * @return list<Campo>
     */
    public static function camposDeDatos(): array
    {
        return [
            Campo::texto('nombre', 'Nombre', maximo: 150),
            Campo::texto('direccion', 'Dirección', maximo: 255),
            Campo::elementoDe('ubigeo_id', 'Ubigeo', Catalogo::Ubigeos)->opcional(),
            Campo::texto('latitud', 'Latitud', maximo: 20)->opcional(),
            Campo::texto('longitud', 'Longitud', maximo: 20)->opcional(),
            Campo::entero('contacto_id', 'Contacto en la sede')->conReglas('exists:party_contactos,id')->opcional(),
            Campo::texto('codigo_establecimiento', 'Código de establecimiento SUNAT', maximo: 4)->opcional(),
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected static function datos(array $datos): DatosSede
    {
        return new DatosSede(
            $datos['nombre'],
            $datos['direccion'],
            ubigeoId: $datos['ubigeo_id'] ?? null,
            latitud: $datos['latitud'] ?? null,
            longitud: $datos['longitud'] ?? null,
            contactoId: $datos['contacto_id'] ?? null,
            codigoEstablecimiento: $datos['codigo_establecimiento'] ?? null,
        );
    }
}
