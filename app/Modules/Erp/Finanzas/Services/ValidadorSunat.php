<?php

declare(strict_types=1);

namespace App\Modules\Erp\Finanzas\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * MVP rápido (t5-finanzas v3, MV-014): la consulta de validez del comprobante en SUNAT (API de consulta integrada).
 * Se activa con las credenciales de la empresa en el .env (SUNAT_CLIENT_ID, SUNAT_CLIENT_SECRET, SUNAT_RUC); sin ellas
 * responde «simulado» y el comprobante queda válido para seguir.
 */
final readonly class ValidadorSunat
{
    /** @return array{estado: string, detalle: string} estado: valido | observado */
    public function validar(string $rucEmisor, string $tipo, string $serieNumero, string $emision, string $total): array
    {
        $id = (string) config('services.sunat.client_id');
        $secreto = (string) config('services.sunat.client_secret');
        $consultante = (string) config('services.sunat.ruc');

        if ($id === '' || $secreto === '' || $consultante === '') {
            return ['estado' => 'valido', 'detalle' => 'Simulado: faltan las credenciales de SUNAT en el .env'];
        }

        try {
            $token = Http::asForm()->timeout(15)->post("https://api-seguridad.sunat.gob.pe/v1/clientesextranet/{$id}/oauth2/token/", [
                'grant_type' => 'client_credentials', 'scope' => 'https://api.sunat.gob.pe/v1/contribuyente/contribuyentes', 'client_id' => $id, 'client_secret' => $secreto,
            ])->json('access_token');
            [$serie, $numero] = explode('-', $serieNumero) + [1 => '0'];
            $r = Http::withToken((string) $token)->timeout(15)->post("https://api.sunat.gob.pe/v1/contribuyente/contribuyentes/{$consultante}/validarcomprobante", [
                'numRuc' => $rucEmisor, 'codComp' => $tipo === 'factura' ? '01' : '03', 'numeroSerie' => $serie, 'numero' => (int) $numero,
                'fechaEmision' => date('d/m/Y', strtotime($emision)), 'monto' => $total,
            ])->json('data');
            $estado = (string) ($r['estadoCp'] ?? '');

            return $estado === '1' ? ['estado' => 'valido', 'detalle' => 'SUNAT: aceptado'] : ['estado' => 'observado', 'detalle' => 'SUNAT: estado '.($estado === '' ? 'desconocido' : $estado)];
        } catch (Throwable $e) {
            return ['estado' => 'observado', 'detalle' => 'SUNAT no respondió: se valida después'];
        }
    }
}
