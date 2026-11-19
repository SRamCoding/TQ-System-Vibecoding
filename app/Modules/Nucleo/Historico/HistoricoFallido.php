<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Historico;

use RuntimeException;

/** El archivo histórico no se pudo escribir o comprobar (plan 1.4y, D6): no se borró nada de la base. */
final class HistoricoFallido extends RuntimeException
{
    public static function sinClave(): self
    {
        return new self('Falta la clave de cifrado (BACKUP_ARCHIVE_PASSWORD): sin ella el histórico no se archiva y no se borra nada.');
    }

    public static function noComprobado(string $archivo): self
    {
        return new self("El archivo «{$archivo}» no se pudo volver a leer igual a lo escrito: no se borró nada.");
    }

    public static function ilegible(): self
    {
        return new self('El archivo no se pudo descifrar: no es del archivo histórico o la clave no es la que lo cifró.');
    }
}
