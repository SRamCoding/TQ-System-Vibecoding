<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\TipoCambio;

use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Excepciones\FuenteTipoCambioNoDisponible;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * TC del dólar que publica SUNAT en un archivo de texto: `dd/mm/aaaa|compra|venta|`. Solo trae el del
 * día; los días anteriores no se pueden pedir (ADR 0012).
 */
final class FuenteSunat implements FuenteTipoCambio
{
    public function __construct(
        private readonly Http $http,
        private readonly string $url,
    ) {}

    public function origen(): OrigenTipoCambio
    {
        return OrigenTipoCambio::Sunat;
    }

    public function vigente(): LecturaTipoCambio
    {
        try {
            $respuesta = $this->http->withUserAgent('Mozilla/5.0 (compatible; TechqukERP/1.0)')
                ->connectTimeout(10)
                ->timeout(20)
                ->get($this->url);
        } catch (ConnectionException) {
            throw new FuenteTipoCambioNoDisponible('SUNAT no respondió al consultar el tipo de cambio.');
        }

        if (! $respuesta->successful()) {
            throw new FuenteTipoCambioNoDisponible("SUNAT respondió con error (HTTP {$respuesta->status()}) al consultar el tipo de cambio.");
        }

        return $this->interpretar(trim($respuesta->body()));
    }

    private function interpretar(string $texto): LecturaTipoCambio
    {
        if (preg_match('#^(\d{2}/\d{2}/\d{4})\|(\d{1,3}(?:\.\d{1,4})?)\|(\d{1,3}(?:\.\d{1,4})?)\|?$#', $texto, $partes) !== 1) {
            throw new FuenteTipoCambioNoDisponible('SUNAT cambió el formato del tipo de cambio: no se reconoce «'.mb_substr($texto, 0, 40).'».');
        }

        try {
            $fecha = CarbonImmutable::createFromFormat('!d/m/Y', $partes[1]);
        } catch (InvalidFormatException) {
            $fecha = null;
        }

        if ($fecha === null || $fecha->format('d/m/Y') !== $partes[1]) {
            throw new FuenteTipoCambioNoDisponible("SUNAT publicó una fecha inválida: «{$partes[1]}».");
        }

        return new LecturaTipoCambio('USD', $fecha, bcadd($partes[2], '0', 4), bcadd($partes[3], '0', 4));
    }
}
