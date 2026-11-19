<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Pestanas;

use InvalidArgumentException;

/**
 * Una pestaña que otro módulo suma a la ficha de la oportunidad (plan 2.21, D11.5 y D12): Cotizaciones, Presupuesto,
 * Actividades, Documentos, Licitación de origen. Como PestanaCuenta: su componente lee los datos de los servicios de su
 * propio módulo.
 */
final readonly class PestanaOportunidad
{
    public function __construct(
        /** Minúsculas, cifras y guiones: nombra su componente de Livewire (`oportunidad.<clave>`). */
        public string $clave,
        public string $titulo,
        /** Obligatorio a propósito: una pestaña para todos los que ven la oportunidad se declara con null, no por olvido. */
        public ?string $permiso,
        /** @var class-string<ComponentePestanaOportunidad> */
        public string $componente,
        /** Ícono propio: ninguno se repite (`docs/convenciones/interfaz.md`). */
        public string $icono,
        /** Menor, más a la izquierda; van después de Historial de etapas y antes del Historial. */
        public int $orden = 100,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]{0,39}$/', $clave) !== 1) {
            throw new InvalidArgumentException("La clave de la pestaña [{$clave}] debe llevar solo minúsculas, cifras y guiones, hasta 40.");
        }

        if (! is_subclass_of($componente, ComponentePestanaOportunidad::class)) {
            throw new InvalidArgumentException("El componente de la pestaña [{$clave}] debe extender ComponentePestanaOportunidad.");
        }
    }

    public function nombreLivewire(): string
    {
        return 'oportunidad.'.$this->clave;
    }
}
