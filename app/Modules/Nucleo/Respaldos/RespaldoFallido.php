<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Respaldos;

use RuntimeException;

/** Un respaldo que no se pudo hacer (plan 1.12, D3). El mensaje nunca lleva datos de la base ni de los adjuntos. */
final class RespaldoFallido extends RuntimeException
{
    public static function sinClave(): self
    {
        return new self('Falta la clave de cifrado de los respaldos (BACKUP_ARCHIVE_PASSWORD): sin ella el respaldo saldría sin cifrar.');
    }

    public static function alRespaldar(): self
    {
        return new self('La librería de respaldos no pudo terminar el respaldo; el detalle está en el registro de errores.');
    }

    public static function sinArchivo(string $disco): self
    {
        return new self("El respaldo terminó, pero no aparece en el disco «{$disco}».");
    }
}
