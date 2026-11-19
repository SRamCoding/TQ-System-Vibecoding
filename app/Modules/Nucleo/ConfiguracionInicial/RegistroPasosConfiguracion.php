<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\ConfiguracionInicial;

use InvalidArgumentException;

/**
 * Los pasos del asistente de configuración inicial, en el orden en que se registran (plan 1.13, D5). El núcleo trae los
 * suyos; cada módulo suma los propios desde su provider con `callAfterResolving`, como el núcleo, sin tocar el
 * asistente.
 */
final class RegistroPasosConfiguracion
{
    /** @var array<string, DefinicionPaso> */
    private array $pasos = [];

    public function registrar(DefinicionPaso $paso): void
    {
        if (isset($this->pasos[$paso->clave])) {
            throw new InvalidArgumentException("El paso de configuración «{$paso->clave}» ya está registrado.");
        }

        $this->pasos[$paso->clave] = $paso;
    }

    /** @return array<string, DefinicionPaso> */
    public function pasos(): array
    {
        return $this->pasos;
    }

    public function paso(string $clave): ?DefinicionPaso
    {
        return $this->pasos[$clave] ?? null;
    }
}
