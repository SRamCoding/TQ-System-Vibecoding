<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

/**
 * Consulta al padrón de SUNAT para precargar el alta de una party después de validar el número (plan 1.3,
 * D12). Se llama fuera de toda transacción, como FuenteTipoCambio. Hoy la única implementación es SinPadron:
 * construir la integración es escribir otra y enlazarla en NucleoServiceProvider.
 */
interface FuentePadronContribuyentes
{
    public function consultar(string $codigoTipoDocumento, string $numero): ?DatosPadron;
}
