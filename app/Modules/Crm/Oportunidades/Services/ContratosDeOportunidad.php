<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Services;

/**
 * Lo que Contratos hace cuando se anula una oportunidad ganada (C3 ampliada, propuesta en el ADR 0020; plan 2.21, D1).
 * Se llama dentro de la transacción que anula, después de bloquear la oportunidad: bloquea el contrato de esa
 * oportunidad y, si está en borrador, lo anula con el mismo motivo y pide a C2 anular el proyecto en preparación; si ya
 * avanzó, falla con un `ErrorDeNegocio` («Primero anula el contrato CT-…») y la anulación entera se deshace. Orden de
 * bloqueo: oportunidad → contrato → proyecto → cuenta. Contratos (área 3) registra la real; mientras, vale
 * `ContratosSinModulo`.
 */
interface ContratosDeOportunidad
{
    public function anularBorrador(int $oportunidadId, string $motivo): void;

    /**
     * La etiqueta del contrato de cada ganada que ya lo tiene («CT-2026-0012»), en lote, para la sala de ganadas de la
     * faja (plan 2.21b, D9). Las que no tienen contrato no vienen: la tabla dice «—».
     *
     * @param  list<int>  $oportunidadIds
     * @return array<int, string> por id de oportunidad
     */
    public function etiquetasDeContratos(array $oportunidadIds): array;

    /**
     * Si esta versión tiene datos reales de contratos (plan 2.21b, paso 5; decisión del desarrollador, 2026-11-10). La de
     * fábrica (`ContratosSinModulo`) dice que no, y mientras tanto la sala de ganadas no muestra la columna «Contrato»
     * (una columna llena de «—» no dice nada); aparece sola cuando Contratos registra la suya.
     */
    public function tieneDatos(): bool;
}
