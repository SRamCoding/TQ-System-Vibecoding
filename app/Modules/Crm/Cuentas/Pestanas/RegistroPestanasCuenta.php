<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\Pestanas;

use App\Modules\Nucleo\Models\User;
use InvalidArgumentException;
use Livewire\Livewire;

/**
 * Las pestañas y los botones de cabecera que otros módulos suman a la ficha de la cuenta (plan 2.20, D12), como
 * RegistroPestanasFicha del núcleo. Cada persona ve solo los de los módulos en que tiene permiso.
 */
final class RegistroPestanasCuenta
{
    /** @var array<string, PestanaCuenta> */
    private array $pestanas = [];

    /** @var array<string, BotonCuenta> */
    private array $botones = [];

    public function registrar(PestanaCuenta $pestana): void
    {
        if (isset($this->pestanas[$pestana->clave])) {
            throw new InvalidArgumentException("La pestaña de cuenta [{$pestana->clave}] ya está registrada.");
        }

        $this->pestanas[$pestana->clave] = $pestana;
        Livewire::component($pestana->nombreLivewire(), $pestana->componente);
    }

    public function registrarBoton(BotonCuenta $boton): void
    {
        if (isset($this->botones[$boton->clave])) {
            throw new InvalidArgumentException("El botón de cuenta [{$boton->clave}] ya está registrado.");
        }

        $this->botones[$boton->clave] = $boton;
    }

    /**
     * Las que la persona puede ver, en su orden (a igual orden, el de registro).
     *
     * @return list<PestanaCuenta>
     */
    public function pestanasPara(?User $usuario): array
    {
        return self::ordenadas(array_filter($this->pestanas, fn (PestanaCuenta $p): bool => $this->puedeVer($usuario, $p->permiso)));
    }

    /**
     * @return list<BotonCuenta>
     */
    public function botonesPara(?User $usuario): array
    {
        return self::ordenadas(array_filter($this->botones, fn (BotonCuenta $b): bool => $this->puedeVer($usuario, $b->permiso)));
    }

    public function puedeVer(?User $usuario, ?string $permiso): bool
    {
        return $usuario !== null && ($permiso === null || $usuario->can($permiso));
    }

    public function pestanaPorNombre(string $nombre): ?PestanaCuenta
    {
        foreach ($this->pestanas as $pestana) {
            if ($pestana->nombreLivewire() === $nombre) {
                return $pestana;
            }
        }

        return null;
    }

    /**
     * @template T of PestanaCuenta|BotonCuenta
     *
     * @param  array<string, T>  $elementos
     * @return list<T>
     */
    private static function ordenadas(array $elementos): array
    {
        $lista = array_values($elementos);
        usort($lista, fn (PestanaCuenta|BotonCuenta $a, PestanaCuenta|BotonCuenta $b): int => $a->orden <=> $b->orden);

        return $lista;
    }
}
