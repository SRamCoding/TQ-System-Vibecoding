<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones\Parties;

use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Parties\DatosRedSocial;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\RedesSocialesService;

/**
 * Base de las acciones sobre las redes sociales de una party o de sus contactos (plan 1.4o, D2). Clave
 * `nucleo.parties.<operacion>`, permiso `parties.gestionar-redes`. Las de una persona del equipo no pasan por aquí
 * (las rechaza el servicio): las cambia ella misma desde «Mis datos». Las reglas están en RedesSocialesService.
 */
abstract class AccionRedSocial extends Accion
{
    public function __construct(protected readonly RedesSocialesService $redes) {}

    abstract protected function operacion(): string;

    public function clave(): string
    {
        return 'nucleo.parties.'.$this->operacion();
    }

    public function permiso(): string
    {
        return 'parties.gestionar-redes';
    }

    protected function campoRed(): Campo
    {
        return Campo::entero('red_id', 'Red')->conReglas('exists:party_redes_sociales,id');
    }

    protected function campoVersion(): Campo
    {
        return Campo::entero('version', 'Versión')->conReglas('min:1')->deControl();
    }

    /**
     * @return list<Campo>
     */
    public static function camposDeDatos(): array
    {
        return [
            Campo::elementoDe('red_social_id', 'Red', Catalogo::RedesSociales, detalle: 'detalle'),
            Campo::texto('detalle', '¿Cuál red?', maximo: 100)->opcional(),
            Campo::texto('enlace', 'Enlace', maximo: 500),
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    protected static function datos(array $datos): DatosRedSocial
    {
        return new DatosRedSocial($datos['red_social_id'], $datos['enlace'], (bool) ($datos['es_privada'] ?? false), $datos['detalle'] ?? null);
    }
}
