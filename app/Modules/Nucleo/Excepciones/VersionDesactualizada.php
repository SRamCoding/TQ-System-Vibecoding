<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/**
 * Bloqueo optimista: otra persona guardó el registro después de que este usuario lo abrió. Sirve para
 * todo documento editable con columna `version` (parámetros hoy; cotizaciones, presupuestos… después).
 */
final class VersionDesactualizada extends ErrorDeNegocio
{
    public static function de(string $que): self
    {
        return new self("Otra persona cambió {$que} mientras lo editabas. Vuelve a abrirlo para ver el valor actual.");
    }
}
