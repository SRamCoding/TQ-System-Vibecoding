<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Services;

/**
 * Lo que se escribe al crear una cuenta (plan 2.20, D2). La identidad (razón social o nombres, nombre comercial,
 * rubro) solo se usa si la ficha no existe; si existe, se corrige en Personas y empresas.
 */
final readonly class DatosCuenta
{
    public function __construct(
        public string $ruc,
        public int $ejecutivoUserId,
        public int $origenComercialId,
        /** Asigna el tipo «Entidad pública» a la ficha si no lo tiene (D3). */
        public bool $esPublica = false,
        public ?int $nivelGobiernoId = null,
        public ?string $notas = null,
        public ?string $razonSocial = null,
        public ?string $nombres = null,
        public ?string $apellidos = null,
        public ?string $nombreComercial = null,
        public ?int $rubroId = null,
        /** Plan 2.0, D5: cuál es, con el origen «Otro». */
        public ?string $origenDetalle = null,
    ) {}
}
