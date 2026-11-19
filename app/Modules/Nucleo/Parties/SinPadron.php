<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

/** Mientras no se integre el padrón de SUNAT: no hay nada que precargar. */
final class SinPadron implements FuentePadronContribuyentes
{
    public function consultar(string $codigoTipoDocumento, string $numero): ?DatosPadron
    {
        return null;
    }
}
