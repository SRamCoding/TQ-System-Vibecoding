<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Services;

use App\Modules\Nucleo\Ia\ArchivoParaIa;
use App\Modules\Nucleo\Ia\ClienteAnthropic;
use App\Modules\Nucleo\Ia\PedidoModeloIa;
use Throwable;

/**
 * MVP rápido (t5-finanzas v3, MV-014): lee una nota o boleta por foto con IA (la IA sugiere; una persona confirma).
 * Llama en vivo solo si hay clave de Anthropic en el .env (regla 7 de AGENTS.md); si no, null y el gasto se escribe a
 * mano. Atajo: llama al cliente del modelo directo, sin el proceso registrado ni la cola del núcleo.
 */
final readonly class LectorDeNotas
{
    /** @return array{fecha: ?string, comprobante: ?string, descripcion: ?string, monto: ?string}|null */
    public function leer(string $mime, string $contenido): ?array
    {
        $clave = (string) (config('nucleo.ia.claves')['IA_CLAVE_ANTHROPIC'] ?? '');

        if ($clave === '') {
            return null;
        }

        try {
            $r = app(ClienteAnthropic::class)->llamar(new PedidoModeloIa(
                (string) config('nucleo.ia.modelo_notas', 'claude-haiku-5-5'), 400,
                'Lee esta nota de venta, boleta o factura peruana y devuelve la fecha (AAAA-MM-DD), el número del comprobante, una descripción corta de lo comprado y el total en soles. Si un dato no se lee, déjalo vacío.',
                '', [new ArchivoParaIa($mime, $contenido)],
                ['type' => 'object', 'properties' => ['fecha' => ['type' => 'string'], 'comprobante' => ['type' => 'string'], 'descripcion' => ['type' => 'string'], 'monto' => ['type' => 'string']], 'required' => ['fecha', 'comprobante', 'descripcion', 'monto']],
                60,
            ), $clave);

            return $r->salida === null ? null : array_map(fn ($v) => $v === '' ? null : (string) $v, $r->salida + ['fecha' => null, 'comprobante' => null, 'descripcion' => null, 'monto' => null]);
        } catch (Throwable) {
            return null;
        }
    }
}
