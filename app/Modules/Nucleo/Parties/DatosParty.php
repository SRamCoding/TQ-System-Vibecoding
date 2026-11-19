<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

use App\Modules\Nucleo\Enums\TipoPersona;

/**
 * Lo que se escribe al crear una party (plan 1.3, D4 a D7). El tipo de persona solo se da cuando el documento
 * no lo dice; en una natural, la razón social la arma el servicio. Las marcas tributarias no van: nacen en
 * falso y tienen su propia acción (D7). Estado y condición SUNAT los da solo un proceso (padrón).
 */
final readonly class DatosParty
{
    public function __construct(
        public string $codigoTipoDocumento,
        public string $numeroDocumento,
        public ?TipoPersona $tipo = null,
        public ?string $razonSocial = null,
        public ?string $nombreComercial = null,
        public ?string $nombres = null,
        public ?string $apellidos = null,
        public ?string $direccionFiscal = null,
        public ?int $ubigeoId = null,
        public ?string $telefono = null,
        public ?string $email = null,
        public ?string $estadoSunat = null,
        public ?string $condicionSunat = null,
        /** Plan 2.20a, N4: la sección CIIU (`rubros`), si se conoce. */
        public ?int $rubroId = null,
    ) {}
}
