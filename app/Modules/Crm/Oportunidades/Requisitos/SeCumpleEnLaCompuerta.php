<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Requisitos;

/**
 * Un requisito que se puede cumplir desde la compuerta del avance (plan 2.21d, D8), con la acción de su módulo: emitir
 * la cotización aprobada, aceptar la emitida. Los de la propia oportunidad (necesidad, presupuesto, decisor) se rellenan
 * como campos del mismo avance, así la versión tomada al arrastrar no cambia en medio.
 */
interface SeCumpleEnLaCompuerta extends RequisitoDeEtapa
{
    /** Lo que se puede hacer hoy para cumplirlo, o null si no hay nada a la mano (falta crear la cotización, por ejemplo). */
    public function formaDeCumplir(int $oportunidadId): ?FormaDeCumplir;
}
