<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Pestanas;

use App\Modules\Nucleo\Models\User;
use InvalidArgumentException;
use Livewire\Livewire;

/**
 * Las pestañas que otros módulos suman a la ficha del proyecto (plan 2.10, D3), como RegistroPestanasCuenta. Cada
 * persona ve solo las de los módulos en que tiene permiso.
 */
final class RegistroPestanasProyecto
{
    /** @var array<string, PestanaProyecto> */
    private array $pestanas = [];

    public function registrar(PestanaProyecto $pestana): void
    {
        if (isset($this->pestanas[$pestana->clave])) {
            throw new InvalidArgumentException("La pestaña de proyecto [{$pestana->clave}] ya está registrada.");
        }

        $this->pestanas[$pestana->clave] = $pestana;
        Livewire::component($pestana->nombreLivewire(), $pestana->componente);
    }

    /**
     * Las que la persona puede ver, en su orden (a igual orden, el de registro).
     *
     * @return list<PestanaProyecto>
     */
    public function pestanasPara(?User $usuario): array
    {
        $lista = array_values(array_filter($this->pestanas, fn (PestanaProyecto $p): bool => $this->puedeVer($usuario, $p->permiso)));
        usort($lista, fn (PestanaProyecto $a, PestanaProyecto $b): int => $a->orden <=> $b->orden);

        return $lista;
    }

    public function puedeVer(?User $usuario, ?string $permiso): bool
    {
        return $usuario !== null && ($permiso === null || $usuario->can($permiso));
    }

    public function pestanaPorNombre(string $nombre): ?PestanaProyecto
    {
        foreach ($this->pestanas as $pestana) {
            if ($pestana->nombreLivewire() === $nombre) {
                return $pestana;
            }
        }

        return null;
    }
}
