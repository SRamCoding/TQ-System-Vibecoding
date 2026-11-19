<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Aprobaciones;

use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Enums\FirmanteAprobacion;

/**
 * Los datos de una regla de aprobación como los escribe un administrador (plan 1.7). Los pasos van en orden; cada uno
 * es `documento` (la jefatura del documento) o `unidad:<id>` (la jefatura de esa unidad), como los recibe la acción.
 */
final readonly class DatosRegla
{
    /**
     * @param  list<string>  $pasos
     */
    public function __construct(
        public string $nombre,
        public string $documentoType,
        public string $transicionCodigo,
        public ?string $montoMasDe,
        public ?string $montoHasta,
        public ?string $topeAutoaprobacion,
        public array $pasos,
        /** Plan 2.2, D5: además del monto, cuándo entra. */
        public CondicionRegla $condicion = CondicionRegla::Ninguna,
    ) {}

    /**
     * @return list<array{firmante: FirmanteAprobacion, unidad: ?int}>
     */
    public function pasosLeidos(): array
    {
        return array_map(fn (string $paso): array => $paso === 'documento'
            ? ['firmante' => FirmanteAprobacion::UnidadDelDocumento, 'unidad' => null]
            : ['firmante' => FirmanteAprobacion::UnidadFija, 'unidad' => (int) substr($paso, strlen('unidad:'))], $this->pasos);
    }
}
