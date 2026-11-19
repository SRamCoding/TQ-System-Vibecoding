<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

use Filament\Pages\Page;
use Filament\Resources\Resource as RecursoFilament;

/**
 * Pantallas de Filament que cada módulo declara para un panel concreto, desde el `register()` de su
 * provider. Cada PanelProvider toma solo las suyas: un panel nuevo (portal de clientes, de técnicos)
 * no recibe pantallas que no le corresponden.
 */
final class RegistroPantallas
{
    /** @var array<string, list<class-string<Page>>> */
    private array $paginas = [];

    /** @var array<string, list<class-string<RecursoFilament>>> */
    private array $recursos = [];

    /**
     * @param  list<class-string<Page>>  $paginas
     * @param  list<class-string<RecursoFilament>>  $recursos
     */
    public function registrar(string $panel, array $paginas = [], array $recursos = []): void
    {
        $this->paginas[$panel] = [...($this->paginas[$panel] ?? []), ...$paginas];
        $this->recursos[$panel] = [...($this->recursos[$panel] ?? []), ...$recursos];
    }

    /**
     * @return list<class-string<Page>>
     */
    public function paginas(string $panel): array
    {
        return $this->paginas[$panel] ?? [];
    }

    /**
     * @return list<class-string<RecursoFilament>>
     */
    public function recursos(string $panel): array
    {
        return $this->recursos[$panel] ?? [];
    }
}
