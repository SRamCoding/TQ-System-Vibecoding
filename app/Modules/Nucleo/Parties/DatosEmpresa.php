<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

use App\Modules\Nucleo\Models\Adjunto;

/**
 * Los datos de Techquk como los imprime un documento (plan 1.15, D1), tomados de su ficha. Sin cuentas bancarias: cada
 * documento elige la suya entre las de la ficha.
 */
final readonly class DatosEmpresa
{
    /**
     * @param  ?string  $direccionFiscal  con su distrito, provincia y departamento, si la ficha los tiene
     * @param  list<ResumenRedSocial>  $redes  las públicas, sin el sitio web, en el orden del catálogo
     */
    public function __construct(
        public int $partyId,
        public string $razonSocial,
        public ?string $nombreComercial,
        public string $ruc,
        public ?string $direccionFiscal,
        public ?string $telefono,
        public ?string $correo,
        public ?string $sitioWeb,
        public array $redes,
        public ?Adjunto $logo,
    ) {}

    /**
     * Lo que un documento echaría de menos (D4): «Sin logo», «Sin teléfono». No impide nada; el asistente lo muestra.
     *
     * @return list<string>
     */
    public function faltantes(): array
    {
        return array_keys(array_filter([
            'Sin logo' => $this->logo === null,
            'Sin dirección fiscal' => $this->direccionFiscal === null,
            'Sin teléfono' => $this->telefono === null,
            'Sin correo' => $this->correo === null,
            'Sin sitio web' => $this->sitioWeb === null,
        ]));
    }
}
