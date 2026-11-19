<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Aprobaciones;

use InvalidArgumentException;

/**
 * Los resúmenes de «Mis aprobaciones», por tipo de documento (su alias del morph map). Cada módulo registra el suyo
 * desde su provider (plan 2.22, P6); la bandeja es una sola para todos: cotizaciones, requerimientos, órdenes, gastos.
 */
final class RegistroResumenesAprobacion
{
    /** @var array<string, class-string<ResumenParaAprobar>> */
    private array $resumenes = [];

    /** @param  class-string<ResumenParaAprobar>  $resumen */
    public function registrar(string $documentoType, string $resumen): void
    {
        if (isset($this->resumenes[$documentoType])) {
            throw new InvalidArgumentException("El resumen de [{$documentoType}] ya está registrado.");
        }

        if (! is_subclass_of($resumen, ResumenParaAprobar::class)) {
            throw new InvalidArgumentException("El resumen de [{$documentoType}] debe implementar ResumenParaAprobar.");
        }

        $this->resumenes[$documentoType] = $resumen;
    }

    public function para(string $documentoType): ?ResumenParaAprobar
    {
        $clase = $this->resumenes[$documentoType] ?? null;

        return $clase === null ? null : app($clase);
    }
}
