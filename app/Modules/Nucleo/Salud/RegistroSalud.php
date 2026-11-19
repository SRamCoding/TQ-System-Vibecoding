<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Salud;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/** Los recuadros de la página de salud (plan 1.12, D2), en el orden en que se registran. */
final class RegistroSalud
{
    /** @var array<string, class-string<ComprobacionSalud>> */
    private array $comprobaciones = [];

    public function __construct(private readonly Container $contenedor) {}

    /** @param class-string<ComprobacionSalud> $comprobacion */
    public function registrar(string $comprobacion): void
    {
        $clave = $this->contenedor->make($comprobacion)->clave();

        if (isset($this->comprobaciones[$clave])) {
            throw new InvalidArgumentException("La comprobación de salud «{$clave}» ya está registrada.");
        }

        $this->comprobaciones[$clave] = $comprobacion;
    }

    /** @return array<string, ComprobacionSalud> */
    public function comprobaciones(): array
    {
        return array_map(fn (string $clase): ComprobacionSalud => $this->contenedor->make($clase), $this->comprobaciones);
    }
}
