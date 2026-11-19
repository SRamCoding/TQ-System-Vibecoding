<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

use App\Modules\Erp\Presupuestos\Excepciones\PresupuestoRechazado;
use App\Modules\Nucleo\Models\User;

/**
 * C8 · El presupuesto de una cotización (ADR 0020; plan 2.9, D7). Comercial arma la cotización sobre el presupuesto
 * real, sin copiarlo, y solo por aquí. La real es `PresupuestosEnBase`; en pruebas se puede poner
 * `EnMemoria\PresupuestosEnMemoria` con `$this->app->instance()`. Las dos pasan las mismas pruebas del contrato
 * (`tests/Modules/Erp/Presupuestos/ContratoPresupuestosTest.php`).
 */
interface PresupuestosService
{
    /**
     * Una versión del presupuesto: encabezado con porcentajes y montos, y sus partidas a costo directo (gastos generales
     * y utilidad van al pie, no dentro de cada partida, como se presenta en obra pública).
     *
     * @throws PresupuestoNoEncontrado si no existe
     */
    public function resumen(int $presupuestoId): ResumenPresupuesto;

    /**
     * Los presupuestos de una oportunidad que una cotización puede enlazar (plan 2.22, D8): las versiones en borrador u
     * ofertadas, por número y de la versión más reciente a la más antigua.
     *
     * @return list<ResumenPresupuesto>
     */
    public function deOportunidad(int $oportunidadId): array;

    /**
     * Lo pasa a ofertado (congelado) al emitirse la cotización que lo usa (plan 2.22, D8), a nombre de quien emite.
     * Idempotente: si ya está ofertado o aprobado, no cambia nada. Las versiones anteriores que seguían ofertadas pasan a
     * reemplazadas.
     *
     * @throws PresupuestoNoEncontrado si no existe
     * @throws PresupuestoRechazado si está incompleto, reemplazado o anulado (y nada cambia)
     */
    public function ofertar(int $presupuestoId, User $usuario): ResumenPresupuesto;
}
