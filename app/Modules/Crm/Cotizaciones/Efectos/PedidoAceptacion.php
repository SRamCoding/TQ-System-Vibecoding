<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cotizaciones\Efectos;

use App\Modules\Crm\Cotizaciones\Enums\TipoRespaldo;
use Carbon\CarbonImmutable;
use Closure;
use LogicException;

/**
 * La fecha y el respaldo con que se pidió «Aceptar» (plan 2.22, D5 y P2), para su efecto: como `PedidoTraerAlProyecto`
 * del presupuesto. El servicio los deja aquí mientras corre la transición; fuera de eso no hay pedido.
 */
final class PedidoAceptacion
{
    /** @var array{CarbonImmutable, TipoRespaldo, string, ?int}|null */
    private ?array $pedido = null;

    /**
     * @template T
     *
     * @param  Closure(): T  $tarea
     * @return T
     */
    public function durante(CarbonImmutable $fecha, TipoRespaldo $tipo, string $referencia, ?int $adjuntoId, Closure $tarea): mixed
    {
        $this->pedido = [$fecha, $tipo, $referencia, $adjuntoId];

        try {
            return $tarea();
        } finally {
            $this->pedido = null;
        }
    }

    /** @return array{CarbonImmutable, TipoRespaldo, string, ?int} */
    public function pedido(): array
    {
        return $this->pedido ?? throw new LogicException('«Aceptar» se pide con CotizacionesService::aceptar(), que trae la fecha y el respaldo.');
    }
}
