<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

use App\Modules\Nucleo\Enums\ResultadoLlamadaIa;
use LogicException;

/**
 * El cliente de las pruebas (ia.md: nunca el servicio real). Devuelve las respuestas que la prueba le encola, en orden,
 * y guarda los pedidos para revisarlos. Se registra como implementación `falso` solo en pruebas.
 */
final class ClienteModeloLenguajeFalso implements ClienteModeloLenguaje
{
    /** @var list<RespuestaModeloIa> */
    private array $respuestas = [];

    /** @var list<PedidoModeloIa> */
    public array $pedidos = [];

    /** @param array<string, mixed> $salida */
    public function responderValida(array $salida, int $tokensEntrada = 1000, int $tokensSalida = 200): self
    {
        return $this->responder(new RespuestaModeloIa(ResultadoLlamadaIa::Valida, $tokensEntrada, $tokensSalida, 800, $salida));
    }

    public function responder(RespuestaModeloIa $respuesta): self
    {
        $this->respuestas[] = $respuesta;

        return $this;
    }

    public function llamar(PedidoModeloIa $pedido, string $clave): RespuestaModeloIa
    {
        $this->pedidos[] = $pedido;

        return array_shift($this->respuestas) ?? throw new LogicException('El cliente falso de IA no tiene más respuestas encoladas.');
    }
}
