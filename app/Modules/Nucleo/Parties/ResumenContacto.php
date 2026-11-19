<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

/**
 * Lo que otro módulo recibe de un contacto (por ejemplo, el CRM al elegir el contacto de una cotización): el id
 * para guardarlo y una etiqueta para mostrarla («Luis Fernández · Jefe de Logística»). Nunca el modelo.
 */
final readonly class ResumenContacto
{
    public function __construct(
        public int $id,
        public string $etiqueta,
        public bool $esPrincipal,
        public bool $esDecisor,
    ) {}
}
