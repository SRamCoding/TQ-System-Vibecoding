<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

/**
 * MVP rápido (t1-puente-erp): lleva el jefe y las fechas que pidió «Ganada» hasta el efecto de la transición, que
 * corre dentro de la misma transacción. Singleton; se limpia al terminar.
 */
final class PedidoGanar
{
    private ?DatosDelProyectoAlGanar $datos = null;

    /**
     * @template T
     *
     * @param  callable(): T  $paso
     * @return T
     */
    public function durante(?DatosDelProyectoAlGanar $datos, callable $paso): mixed
    {
        $this->datos = $datos;

        try {
            return $paso();
        } finally {
            $this->datos = null;
        }
    }

    public function datos(): ?DatosDelProyectoAlGanar
    {
        return $this->datos;
    }
}
