<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Acciones;

use App\Modules\Nucleo\Acciones\Parties\AccionRedSocial;
use App\Modules\Nucleo\Excepciones\PartyRechazada;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\RedesSocialesService;

/**
 * Base de las acciones con que cada persona del equipo cambia sus propias redes desde «Mis datos» (plan 1.4o, D2 y
 * D3): nunca las de otra, y red por red elige si las ve todo el equipo o solo ella. Sin permiso: son
 * sus propios datos. Las reglas están en RedesSocialesService.
 */
abstract class AccionMiRedSocial extends Accion
{
    public function __construct(protected readonly RedesSocialesService $redes) {}

    /**
     * @return list<Campo>
     */
    protected static function camposDeDatos(): array
    {
        return [
            ...AccionRedSocial::camposDeDatos(),
            Campo::booleano('es_privada', 'Solo la veo yo (oculta para el resto del equipo)'),
        ];
    }

    protected static function campoVersion(): Campo
    {
        return Campo::entero('version', 'Versión')->conReglas('min:1')->deControl();
    }

    /** La ficha de la persona: el usuario de sistema no tiene. */
    protected static function miParty(User $usuario): int
    {
        return $usuario->party_id ?? throw PartyRechazada::cuentaSinFicha();
    }
}
