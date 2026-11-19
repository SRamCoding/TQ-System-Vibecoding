<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Parties;

use App\Modules\Nucleo\Models\User;
use InvalidArgumentException;
use Livewire\Livewire;

/**
 * Las pestañas que otros módulos suman a la ficha (plan 1.4m, D5), como RegistroTarjetasMien. Cada persona ve solo las
 * de los módulos en que tiene permiso.
 */
final class RegistroPestanasFicha
{
    /** @var array<string, PestanaFicha> */
    private array $pestanas = [];

    public function registrar(PestanaFicha $pestana): void
    {
        if (isset($this->pestanas[$pestana->clave])) {
            throw new InvalidArgumentException("La pestaña [{$pestana->clave}] ya está registrada.");
        }

        $this->pestanas[$pestana->clave] = $pestana;
        Livewire::component($pestana->nombreLivewire(), $pestana->componente);
    }

    /**
     * Todas, en su orden (a igual orden, el de registro).
     *
     * @return list<PestanaFicha>
     */
    public function todas(): array
    {
        $todas = array_values($this->pestanas);
        usort($todas, fn (PestanaFicha $a, PestanaFicha $b): int => $a->orden <=> $b->orden);

        return $todas;
    }

    public function puedeVer(?User $usuario, PestanaFicha $pestana): bool
    {
        return $usuario !== null && ($pestana->permiso === null || $usuario->can($pestana->permiso));
    }

    public function porNombre(string $nombre): ?PestanaFicha
    {
        foreach ($this->pestanas as $pestana) {
            if ($pestana->nombreLivewire() === $nombre) {
                return $pestana;
            }
        }

        return null;
    }
}
