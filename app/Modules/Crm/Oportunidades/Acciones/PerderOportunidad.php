<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

use App\Modules\Crm\Oportunidades\Enums\GanadorConocido;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use LogicException;

/**
 * Perder (plan 2.21, D4): motivo de su lista y «¿Lo ganó otra empresa?» (Sí, No, No se sabe); con «Sí», el competidor y
 * su precio con moneda, o «precio desconocido». La acción propia de la transición «perder».
 */
final class PerderOportunidad extends Accion
{
    public function clave(): string
    {
        return DefinicionOportunidad::CLAVE.'.perder';
    }

    public function nombre(): string
    {
        return 'Perder oportunidad';
    }

    public function campos(): array
    {
        return [
            Campo::entero('oportunidad_id', 'Oportunidad')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl(),
            Campo::motivoDe('motivo_id', 'Motivo', (string) (new DefinicionOportunidad)->familiaDeMotivos((new DefinicionOportunidad)->transicion('perder') ?? throw new LogicException('Falta la transición perder.'))),
            Campo::texto('motivo_detalle', 'Detalle del motivo', maximo: 1000)->opcional(),
            Campo::opcion('ganador_conocido', '¿Lo ganó otra empresa?', GanadorConocido::class),
            Campo::entero('ganador_party_id', 'Empresa que ganó')->opcional(),
            Campo::decimal('precio', 'Precio con que ganó')->opcional(),
            Campo::elementoDe('moneda_id', 'Moneda', Catalogo::Monedas)->opcional(),
            Campo::booleano('precio_desconocido', 'Precio desconocido')->opcional(),
            Campo::texto('comentario', 'Comentario', maximo: 1000)->opcional(),
        ];
    }

    public function permiso(): string
    {
        return $this->clave();
    }

    public function ejecutar(array $datos, User $usuario): HistorialEstado
    {
        return app(OportunidadesService::class)->perder(
            $datos['oportunidad_id'], $datos['version'], new MotivoElegido($datos['motivo_id'], $datos['motivo_detalle'] ?? null),
            $datos['ganador_conocido'], $datos['ganador_party_id'] ?? null, $datos['precio'] ?? null, $datos['moneda_id'] ?? null,
            (bool) ($datos['precio_desconocido'] ?? false), $datos['comentario'] ?? null, $usuario,
        );
    }
}
