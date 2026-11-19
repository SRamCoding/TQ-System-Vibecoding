<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Actividad;

use Carbon\CarbonImmutable;

/** Lo que los aportes dicen de una cuenta; nulo lo que nadie aporta (o la cuenta no tiene). */
final readonly class DatosActividadCuenta
{
    public function __construct(
        public ?int $oportunidadesAbiertas = null,
        public ?CarbonImmutable $ultimoContacto = null,
        public ?CarbonImmutable $proximoContacto = null,
        /** En moneda local, como texto decimal (nunca float). */
        public ?string $facturado = null,
    ) {}

    /** Junta dos aportes: gana lo que no es nulo (cada dato lo aporta un solo módulo). */
    public function con(self $otro): self
    {
        return new self(
            $otro->oportunidadesAbiertas ?? $this->oportunidadesAbiertas,
            $otro->ultimoContacto ?? $this->ultimoContacto,
            $otro->proximoContacto ?? $this->proximoContacto,
            $otro->facturado ?? $this->facturado,
        );
    }
}
