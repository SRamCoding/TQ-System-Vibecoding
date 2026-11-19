<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

use InvalidArgumentException;

/**
 * Una pestaña que otro módulo suma a la ficha de una persona o empresa (plan 1.4m, D5): el CRM sus oportunidades, las
 * compras sus órdenes. La registra el provider de su módulo en RegistroPestanasFicha; su componente lee los datos de
 * los servicios de su propio módulo, nunca de otro (regla 2).
 */
final readonly class PestanaFicha
{
    public function __construct(
        /** Minúsculas, cifras y guiones: nombra su componente de Livewire (`ficha.<clave>`). */
        public string $clave,
        public string $titulo,
        /** Obligatorio a propósito: una pestaña visible para todos se declara con null, no por olvido. */
        public ?string $permiso,
        /** @var class-string<ComponentePestanaFicha> */
        public string $componente,
        /** Ícono propio: ninguno se repite (`docs/convenciones/interfaz.md`). */
        public string $icono,
        /** Menor, más a la izquierda; van después de las del Núcleo y antes del historial. */
        public int $orden = 100,
        /**
         * Plan 2.20a, N6: en qué fichas aparece, por el código de su tipo de documento (`Catalogo::RUC`); null, en todas.
         * Decide cuándo se muestra, no quién la ve: eso es el permiso.
         *
         * @var list<string>|null
         */
        public ?array $tiposDocumento = null,
    ) {
        if ($tiposDocumento !== null && ($tiposDocumento === [] || in_array('', $tiposDocumento, true))) {
            throw new InvalidArgumentException("La pestaña [{$clave}] debe dar al menos un tipo de documento, o null para todas.");
        }

        if (preg_match('/^[a-z][a-z0-9-]{0,39}$/', $clave) !== 1) {
            throw new InvalidArgumentException("La clave de la pestaña [{$clave}] debe llevar solo minúsculas, cifras y guiones, hasta 40.");
        }

        if (! is_subclass_of($componente, ComponentePestanaFicha::class)) {
            throw new InvalidArgumentException("El componente de la pestaña [{$clave}] debe extender ComponentePestanaFicha.");
        }
    }

    /** Si aparece en una ficha con este tipo de documento (su código: `'6'` es el RUC). */
    public function aplicaA(string $codigoTipoDocumento): bool
    {
        return $this->tiposDocumento === null || in_array($codigoTipoDocumento, $this->tiposDocumento, true);
    }

    public function nombreLivewire(): string
    {
        return 'ficha.'.$this->clave;
    }
}
