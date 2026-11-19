<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

use App\Modules\Crm\Oportunidades\Estados\SobreLoPropio;
use App\Modules\Crm\Oportunidades\Models\OportunidadCompetidor;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Agrega un competidor a una oportunidad abierta o cambia uno (plan 2.21, D4; D10: con editar). La de las propias es
 * GuardarCompetidorPropio.
 */
class GuardarCompetidor extends Accion
{
    public function clave(): string
    {
        return 'crm.oportunidades.guardar-competidor';
    }

    public function nombre(): string
    {
        return 'Guardar competidor';
    }

    public function campos(): array
    {
        return [
            Campo::entero('oportunidad_id', 'Oportunidad')->conReglas('min:1'),
            Campo::entero('competidor_id', 'Competidor de la lista')->opcional(),
            Campo::entero('version', 'Versión')->deControl()->opcional(),
            Campo::entero('party_id', 'Empresa')->conReglas('min:1'),
            Campo::decimal('precio', 'Precio')->opcional(),
            Campo::elementoDe('moneda_id', 'Moneda', Catalogo::Monedas)->opcional(),
            Campo::texto('fortalezas', 'Fortalezas', maximo: 2000)->opcional(),
            Campo::texto('debilidades', 'Debilidades', maximo: 2000)->opcional(),
        ];
    }

    public function permiso(): string
    {
        return SobreLoPropio::EDITAR;
    }

    final public function ejecutar(array $datos, User $usuario): OportunidadCompetidor
    {
        return app(OportunidadesService::class)->guardarCompetidor(
            $datos['oportunidad_id'], $datos['competidor_id'] ?? null, $datos['version'] ?? null, $datos['party_id'],
            $datos['precio'] ?? null, $datos['moneda_id'] ?? null, $datos['fortalezas'] ?? null, $datos['debilidades'] ?? null, $usuario,
        );
    }
}
