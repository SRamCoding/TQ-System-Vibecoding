<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Excepciones;

/** Una línea excede su partida y no tiene su motivo (plan 2.2, D3 y D4). No se escribe nada. */
final class ExcesoDePartidaRechazado extends ErrorDeNegocio
{
    /**
     * @param  list<string>  $partidas  «la partida PC-01.02 Cable se excedería en S/ 200.00»
     */
    public static function sinMotivo(array $partidas): self
    {
        return new self(ucfirst(implode('; ', $partidas)).': indica el motivo del exceso y un comentario.');
    }

    /**
     * @param  list<string>  $partidas
     */
    public static function nuevoAlAprobar(array $partidas): self
    {
        return new self(ucfirst(implode('; ', $partidas)).': este exceso apareció después de enviar y su aprobación no lo incluye. Observa el documento para que lo reenvíen con la aprobación del exceso.');
    }

    public static function sinDestino(): self
    {
        return new self('Una línea de costo todavía no tiene a dónde va: elige su destino.');
    }
}
