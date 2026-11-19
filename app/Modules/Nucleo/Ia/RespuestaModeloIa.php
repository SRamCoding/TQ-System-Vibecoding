<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

use App\Modules\Nucleo\Enums\ResultadoLlamadaIa;

/**
 * Lo que volvió de una llamada (plan 1.11, D3): cómo terminó, los tokens que cobró el proveedor (también en las fallidas
 * o inválidas, si los informa) y, si vino bien formada, la salida ya decodificada. Sin el texto del proveedor: un error
 * puede repetir lo que se envió.
 */
final readonly class RespuestaModeloIa
{
    /**
     * @param  ?array<string, mixed>  $salida
     */
    public function __construct(
        public ResultadoLlamadaIa $resultado,
        public int $tokensEntrada,
        public int $tokensSalida,
        public int $duracionMs,
        public ?array $salida = null,
    ) {}
}
