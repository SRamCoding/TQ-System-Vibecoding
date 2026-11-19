<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Pestanas;

use InvalidArgumentException;

/**
 * Una pestaña que otro módulo suma a la ficha del proyecto (plan 2.10, D3): Presupuesto y Partidas de control hoy;
 * Contratos, Compras u Obra después. Como PestanaCuenta: su componente lee los datos de los servicios de su propio
 * módulo, nunca los modelos de Proyectos.
 */
final readonly class PestanaProyecto
{
    public function __construct(
        /** Minúsculas, cifras y guiones: nombra su componente de Livewire (`proyecto.<clave>`). */
        public string $clave,
        public string $titulo,
        /** Obligatorio a propósito: una pestaña para todos los que ven el proyecto se declara con null, no por olvido. */
        public ?string $permiso,
        /** @var class-string<ComponentePestanaProyecto> */
        public string $componente,
        /** Ícono propio: ninguno se repite (`docs/convenciones/interfaz.md`). */
        public string $icono,
        /** Menor, más a la izquierda; van después de Datos y antes del Historial. */
        public int $orden = 100,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]{0,39}$/', $clave) !== 1) {
            throw new InvalidArgumentException("La clave de la pestaña [{$clave}] debe llevar solo minúsculas, cifras y guiones, hasta 40.");
        }

        if (! is_subclass_of($componente, ComponentePestanaProyecto::class)) {
            throw new InvalidArgumentException("El componente de la pestaña [{$clave}] debe extender ComponentePestanaProyecto.");
        }
    }

    public function nombreLivewire(): string
    {
        return 'proyecto.'.$this->clave;
    }
}
