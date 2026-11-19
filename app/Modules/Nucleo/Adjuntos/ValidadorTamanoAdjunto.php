<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Adjuntos;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/**
 * `adjuntos.tamano_maximo_mb` (plan 1.12b, D8): de 1 al techo. El techo es el mismo en nginx, PHP y Livewire
 * (`docker/produccion/`, `config/livewire.php`): un valor mayor se aceptaría aquí y el archivo se cortaría antes de
 * llegar. Subir el techo es cambiar esta constante y reconstruir la imagen.
 */
final readonly class ValidadorTamanoAdjunto implements ValidadorParametro
{
    public const int TECHO_MB = 50;

    public function normalizar(mixed $valor): int
    {
        return (int) $valor;
    }

    public function rechazo(mixed $valor): ?string
    {
        return $valor >= 1 && $valor <= self::TECHO_MB ? null : 'debe estar entre 1 y '.self::TECHO_MB.' MB (el techo del servidor).';
    }
}
