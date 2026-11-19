<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Aprobaciones;

/**
 * Lo que un documento aporta a «Mis aprobaciones» (plan 2.22, P6): su resumen para decidir sin abrirlo y el enlace a su
 * ficha. Lo registra el provider de su módulo en `RegistroResumenesAprobacion`, con datos de sus propios servicios.
 * Un documento sin resumen registrado aparece igual, con su nombre, el paso y el monto de la ronda.
 */
interface ResumenParaAprobar
{
    /**
     * Los datos que se muestran, en orden. Los de costo o margen llevan su grupo sensible: el núcleo los quita para
     * quien no tiene el permiso.
     *
     * @return list<DatoParaAprobar>
     */
    public function datos(int $documentoId): array;

    /** La ficha del documento, si tiene pantalla. */
    public function enlace(int $documentoId): ?string;
}
