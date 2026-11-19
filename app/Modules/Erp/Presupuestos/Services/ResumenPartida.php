<?php

declare(strict_types=1);

namespace App\Modules\Erp\Presupuestos\Services;

/** Una partida vista desde otro módulo (C1, ADR 0020): de solo lectura, nunca el modelo. */
final readonly class ResumenPartida
{
    public function __construct(
        public int $id,
        public int $proyectoId,
        public string $codigo,
        public string $nombre,
        /** «PC-02 › PC-02.01 Cable»: los códigos de sus padres y, al final, su código y nombre. */
        public string $ruta,
        public bool $esNoPresupuestada,
        public int $responsableUserId,
        /** Una ya guardada puede estar desactivada (`PartidasService::resumen()`); `buscar()` solo da activas. */
        public bool $esActiva = true,
    ) {}
}
