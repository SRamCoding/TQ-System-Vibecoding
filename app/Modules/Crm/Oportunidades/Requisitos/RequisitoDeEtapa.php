<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Requisitos;

/**
 * Algo que una oportunidad debe cumplir para avanzar (plan 2.21, D9): «Decisor identificado», «Una cotización emitida».
 * Lo registra el módulo dueño del dato (Oportunidades, Cotizaciones, Actividades) en `RequisitosDeEtapa`. Recibe el id,
 * no el modelo: otro módulo no usa los modelos de Oportunidades.
 */
interface RequisitoDeEtapa
{
    /** Desde qué etapa se exige: `calificada`, `propuesta_enviada`, `negociacion` o `ganada`. */
    public function exigidoAlEntrarEn(): string;

    /** Cómo se lee en el panel y en el mensaje: «Decisor identificado». */
    public function texto(): string;

    public function cumple(int $oportunidadId): bool;
}
