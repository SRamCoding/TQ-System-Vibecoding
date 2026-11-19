<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Imputacion;

/**
 * A dónde va una línea de costo (plan 1.9, D1; regla 3): un centro de costo o, desde la etapa 2, la partida de control
 * de un proyecto (`Erp/Presupuestos`, plan 2.1, D6), sin cambiar a quien imputa.
 */
interface DestinoImputacion
{
    /**
     * Las columnas de `imputaciones` que lo guardan. Las demás de destino las pone en nulo el servicio
     * (`ImputacionService::COLUMNAS_DESTINO`): un destino nuevo suma su columna allí.
     *
     * @return array<string, int|string|null>
     */
    public function columnas(): array;

    /**
     * Revisa que el destino siga aceptando costos y bloquea su fila, dentro de la transacción de `destinar()` (plan
     * 2.1, D6): así nadie lo desactiva mientras se le imputa. Si ya no acepta, lanza `ImputacionRechazada`.
     */
    public function revisarAlDestinar(): void;

    /** Cómo se nombra en los mensajes: «el centro de costo Administración». */
    public function descripcion(): string;
}
