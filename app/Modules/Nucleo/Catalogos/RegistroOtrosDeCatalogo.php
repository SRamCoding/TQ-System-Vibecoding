<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Catalogos;

use App\Modules\Nucleo\Enums\Catalogo;

/**
 * Quién guarda detalles de «Otro» de cada catálogo (plan 2.0, D5). Cada módulo registra su fuente desde su provider.
 */
final class RegistroOtrosDeCatalogo
{
    /** @var array<string, list<class-string<FuenteDeOtros>>> por catálogo */
    private array $fuentes = [];

    /** @param class-string<FuenteDeOtros> $fuente */
    public function registrar(Catalogo $catalogo, string $fuente): void
    {
        $this->fuentes[$catalogo->value][] = $fuente;
    }

    /** @return list<class-string<FuenteDeOtros>> */
    public function de(Catalogo $catalogo): array
    {
        return $this->fuentes[$catalogo->value] ?? [];
    }
}
