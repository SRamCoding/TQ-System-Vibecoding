<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Saludo;

use App\Modules\Nucleo\Enums\Ocasion;
use LogicException;

/**
 * Las variantes del gran momento de cada fecha (plan 1.4d, D7), como RegistroAmbientaciones. Cuál toca cada año lo
 * elige y guarda el servicio del gran momento; aquí solo están las que existen, en el orden en que se registraron.
 */
final class RegistroGranMomento
{
    /** @var array<string, array<string, VarianteGranMomento>> por ocasión y clave */
    private array $variantes = [];

    public function registrar(VarianteGranMomento ...$variantes): void
    {
        foreach ($variantes as $variante) {
            if (isset($this->variantes[$variante->ocasion->value][$variante->clave])) {
                throw new LogicException("La variante «{$variante->clave}» del gran momento ya está registrada.");
            }

            $this->variantes[$variante->ocasion->value][$variante->clave] = $variante;
        }
    }

    /**
     * @return list<VarianteGranMomento>
     */
    public function de(Ocasion $ocasion): array
    {
        return array_values($this->variantes[$ocasion->value] ?? []);
    }

    /** La variante con esa clave, o nula si ya no existe (se retiró del registro). */
    public function variante(Ocasion $ocasion, string $clave): ?VarianteGranMomento
    {
        return $this->variantes[$ocasion->value][$clave] ?? null;
    }
}
