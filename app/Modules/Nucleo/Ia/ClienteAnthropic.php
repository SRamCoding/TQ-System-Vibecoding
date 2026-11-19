<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Ia;

use App\Modules\Nucleo\Enums\ResultadoLlamadaIa;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * La Claude API de Anthropic (plan 1.11, D7) con el HTTP de Laravel, sin paquete nuevo: un pedido a la Messages API con
 * salida estructurada (`output_config.format`, esquema JSON), los archivos como bloques en base64 y la lectura de los
 * tokens de `usage`. El modelo sale del catálogo, nunca de aquí.
 */
final class ClienteAnthropic implements ClienteModeloLenguaje
{
    public const string URL = 'https://api.anthropic.com/v1/messages';

    public const string VERSION_API = '2026-10-21';

    public function llamar(PedidoModeloIa $pedido, string $clave): RespuestaModeloIa
    {
        $inicio = hrtime(true);

        try {
            $respuesta = Http::withHeaders(['x-api-key' => $clave, 'anthropic-version' => self::VERSION_API])
                ->acceptJson()
                ->timeout($pedido->tiempoMaximoSegundos)
                ->post(self::URL, $this->cuerpo($pedido));
        } catch (ConnectionException) {
            return new RespuestaModeloIa(ResultadoLlamadaIa::TiempoAgotado, 0, 0, $this->milisegundos($inicio));
        }

        return $this->leer($respuesta, $this->milisegundos($inicio));
    }

    /** @return array<string, mixed> */
    private function cuerpo(PedidoModeloIa $pedido): array
    {
        $contenido = [];

        foreach ($pedido->archivos as $archivo) {
            $contenido[] = [
                'type' => $archivo->esPdf() ? 'document' : 'image',
                'source' => ['type' => 'base64', 'media_type' => $archivo->tipo, 'data' => base64_encode($archivo->contenido)],
            ];
        }

        $contenido[] = ['type' => 'text', 'text' => $pedido->contexto];

        return [
            'model' => $pedido->modelo,
            'max_tokens' => $pedido->maxTokensSalida,
            'system' => $pedido->prompt,
            'messages' => [['role' => 'user', 'content' => $contenido]],
            'output_config' => ['format' => ['type' => 'json_schema', 'schema' => $pedido->esquema]],
        ];
    }

    private function leer(Response $respuesta, int $duracionMs): RespuestaModeloIa
    {
        $uso = $respuesta->json('usage');
        // Lo que se cobra como entrada, también lo que se escribió o leyó de la caché del proveedor.
        $entrada = is_array($uso) ? (int) ($uso['input_tokens'] ?? 0) + (int) ($uso['cache_creation_input_tokens'] ?? 0) + (int) ($uso['cache_read_input_tokens'] ?? 0) : 0;
        $salida = is_array($uso) ? (int) ($uso['output_tokens'] ?? 0) : 0;

        if (! $respuesta->successful()) {
            return new RespuestaModeloIa($this->resultadoDeError($respuesta), $entrada, $salida, $duracionMs);
        }

        $resultado = match ($respuesta->json('stop_reason')) {
            'end_turn' => null,
            'max_tokens' => ResultadoLlamadaIa::Cortada,
            // Una negativa del modelo no trae la salida pedida.
            default => ResultadoLlamadaIa::Invalida,
        };

        $datos = $resultado === null ? $this->salida($respuesta) : null;

        return new RespuestaModeloIa($datos === null ? ($resultado ?? ResultadoLlamadaIa::Invalida) : ResultadoLlamadaIa::Valida, $entrada, $salida, $duracionMs, $datos);
    }

    /**
     * El primer bloque de texto, decodificado; nulo si no es un objeto JSON.
     *
     * @return ?array<string, mixed>
     */
    private function salida(Response $respuesta): ?array
    {
        foreach ((array) $respuesta->json('content') as $bloque) {
            if (is_array($bloque) && ($bloque['type'] ?? null) === 'text' && is_string($bloque['text'] ?? null)) {
                try {
                    $datos = json_decode($bloque['text'], true, 64, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    return null;
                }

                return is_array($datos) && ! array_is_list($datos) ? $datos : null;
            }
        }

        return null;
    }

    /**
     * Saturación (429, 529) se reintenta una vez; crédito agotado o clave inválida no (D7). Por el tipo de error del
     * proveedor, y si no viene, por el estado HTTP.
     */
    private function resultadoDeError(Response $respuesta): ResultadoLlamadaIa
    {
        // Ajuste del 2026-10-28: el crédito agotado también llega como 400 con este mensaje.
        if ($respuesta->status() === 400 && str_contains(mb_strtolower((string) $respuesta->json('error.message')), 'credit balance is too low')) {
            return ResultadoLlamadaIa::SinCredito;
        }

        return match ($respuesta->json('error.type')) {
            'billing_error' => ResultadoLlamadaIa::SinCredito,
            'authentication_error', 'permission_error' => ResultadoLlamadaIa::ClaveInvalida,
            'rate_limit_error', 'overloaded_error' => ResultadoLlamadaIa::Saturado,
            default => match ($respuesta->status()) {
                402 => ResultadoLlamadaIa::SinCredito,
                401, 403 => ResultadoLlamadaIa::ClaveInvalida,
                429, 529 => ResultadoLlamadaIa::Saturado,
                408, 504 => ResultadoLlamadaIa::TiempoAgotado,
                default => ResultadoLlamadaIa::Error,
            },
        };
    }

    private function milisegundos(int|float $inicio): int
    {
        return (int) round((hrtime(true) - $inicio) / 1_000_000);
    }
}
