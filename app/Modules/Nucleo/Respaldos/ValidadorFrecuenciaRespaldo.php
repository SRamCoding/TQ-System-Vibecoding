<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Respaldos;

use App\Modules\Nucleo\RegistroParametros\ValidadorParametro;

/** `respaldos.frecuencia` (plan 1.12, D3): «diaria» o «semanal». */
final readonly class ValidadorFrecuenciaRespaldo implements ValidadorParametro
{
    public function normalizar(mixed $valor): string
    {
        return mb_strtolower(trim((string) $valor));
    }

    public function rechazo(mixed $valor): ?string
    {
        return FrecuenciaRespaldo::tryFrom((string) $valor) === null ? 'escribe «diaria» o «semanal».' : null;
    }
}
