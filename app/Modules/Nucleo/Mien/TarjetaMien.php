<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Mien;

use InvalidArgumentException;

/**
 * Una tarjeta de «Mien», el tablero personal (plan 1.4l, D3). La registra el provider de su módulo en
 * RegistroTarjetasMien; su componente lee los datos de los servicios de ese mismo módulo, nunca de otro.
 * Muestra algo del negocio (pendientes, aprobaciones, alertas, números del área), nunca atajos ni ajustes de la
 * aplicación (D4).
 */
final readonly class TarjetaMien
{
    public function __construct(
        /** Minúsculas, cifras y guiones: nombra su componente de Livewire (`mien.<clave>`). */
        public string $clave,
        public string $titulo,
        /** Obligatorio a propósito: una tarjeta visible para todos se declara con null, no por olvido. */
        public ?string $permiso,
        /** @var class-string<ComponenteTarjetaMien> */
        public string $componente,
        /** Menor, más arriba; a igual orden, el de registro. */
        public int $orden = 100,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]{0,39}$/', $clave) !== 1) {
            throw new InvalidArgumentException("La clave de la tarjeta [{$clave}] debe llevar solo minúsculas, cifras y guiones, hasta 40.");
        }

        if (! is_subclass_of($componente, ComponenteTarjetaMien::class)) {
            throw new InvalidArgumentException("El componente de la tarjeta [{$clave}] debe extender ComponenteTarjetaMien.");
        }
    }

    public function nombreLivewire(): string
    {
        return 'mien.'.$this->clave;
    }
}
