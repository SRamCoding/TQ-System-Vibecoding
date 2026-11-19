<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Acciones;

use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Secciones\SeccionDeOportunidad;
use App\Modules\Crm\Oportunidades\Secciones\SeccionesDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Accion;
use App\Modules\Nucleo\RegistroAcciones\Campo;

/**
 * Avanzar una etapa (plan 2.21, D1, D9): la acción propia de calificar, enviar la propuesta y negociar. Su clave es la
 * de la transición y su permiso, el de la transición. Completa lo que falta en el mismo panel.
 */
abstract class AvanzarOportunidad extends Accion
{
    abstract protected function transicion(): string;

    public function clave(): string
    {
        return DefinicionOportunidad::CLAVE.'.'.$this->transicion();
    }

    public function campos(): array
    {
        return [
            Campo::entero('oportunidad_id', 'Oportunidad')->conReglas('min:1'),
            Campo::entero('version', 'Versión')->deControl(),
            Campo::booleano('necesidad_confirmada', 'Necesidad confirmada')->opcional(),
            // Plan 2.21d, D8: la compuerta se rellena ahí mismo: cómo se confirmó y la certificación del presupuesto.
            Campo::texto('necesidad_detalle', '¿Cómo se confirmó?', maximo: 500)->opcional(),
            Campo::booleano('presupuesto_confirmado', 'Presupuesto confirmado')->opcional(),
            Campo::archivo('certificacion_presupuestal', 'Certificación presupuestal')->opcional(),
            Campo::entero('decisor_contacto_id', 'Decisor')->opcional(),
            Campo::texto('comentario', 'Comentario', maximo: 1000)->opcional(),
            ...app(SeccionesDeOportunidad::class)->campos(SeccionDeOportunidad::AL_AVANZAR),
        ];
    }

    public function permiso(): string
    {
        return $this->clave();
    }

    final public function ejecutar(array $datos, User $usuario): HistorialEstado
    {
        return app(OportunidadesService::class)->avanzar(
            $datos['oportunidad_id'], $datos['version'], $this->transicion(),
            $datos['necesidad_confirmada'] ?? null, $datos['presupuesto_confirmado'] ?? null, $datos['decisor_contacto_id'] ?? null,
            $datos, $datos['comentario'] ?? null, $usuario,
        );
    }
}
