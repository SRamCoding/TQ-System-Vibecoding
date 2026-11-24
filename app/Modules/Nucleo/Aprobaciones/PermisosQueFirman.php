<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Aprobaciones;

use App\Modules\Nucleo\Models\User;

/**
 * Observación 9.5 (2): además de la jefatura que dice la regla, puede firmar (aprobar u observar) una transición quien
 * tenga el permiso que el módulo del documento registra aquí (p. ej. `crm.cotizaciones.aprobar-margen`).
 */
final class PermisosQueFirman
{
    /** @var array<string, string> «tipo:transición» → permiso */
    private array $permisos = [];

    public function registrar(string $documentoType, string $transicion, string $permiso): void
    {
        $this->permisos["{$documentoType}:{$transicion}"] = $permiso;
    }

    public function permiso(string $documentoType, string $transicion): ?string
    {
        return $this->permisos["{$documentoType}:{$transicion}"] ?? null;
    }

    public function permite(User $usuario, string $documentoType, string $transicion): bool
    {
        $permiso = $this->permiso($documentoType, $transicion);

        return $permiso !== null && $usuario->can($permiso);
    }
}
