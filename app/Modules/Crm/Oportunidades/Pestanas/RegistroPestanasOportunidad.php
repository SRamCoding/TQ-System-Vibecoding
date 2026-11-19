<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Pestanas;

use App\Modules\Nucleo\Models\User;
use InvalidArgumentException;
use Livewire\Livewire;

/**
 * Las pestañas y los botones de cabecera que otros módulos suman a la ficha de la oportunidad (plan 2.21, D11.5 y D12), como
 * RegistroPestanasFicha del núcleo. Cada persona ve solo los de los módulos en que tiene permiso.
 */
final class RegistroPestanasOportunidad
{
    /** @var array<string, PestanaOportunidad> */
    private array $pestanas = [];

    /** @var array<string, BotonOportunidad> */
    private array $botones = [];

    public function registrar(PestanaOportunidad $pestana): void
    {
        if (isset($this->pestanas[$pestana->clave])) {
            throw new InvalidArgumentException("La pestaña de oportunidad [{$pestana->clave}] ya está registrada.");
        }

        $this->pestanas[$pestana->clave] = $pestana;
        Livewire::component($pestana->nombreLivewire(), $pestana->componente);
    }

    public function registrarBoton(BotonOportunidad $boton): void
    {
        if (isset($this->botones[$boton->clave])) {
            throw new InvalidArgumentException("El botón de oportunidad [{$boton->clave}] ya está registrado.");
        }

        $this->botones[$boton->clave] = $boton;
    }

    /**
     * Las que la persona puede ver, en su orden (a igual orden, el de registro).
     *
     * @return list<PestanaOportunidad>
     */
    public function pestanasPara(?User $usuario): array
    {
        return self::ordenadas(array_filter($this->pestanas, fn (PestanaOportunidad $p): bool => $this->puedeVer($usuario, $p->permiso)));
    }

    /**
     * @return list<BotonOportunidad>
     */
    public function botonesPara(?User $usuario): array
    {
        return self::ordenadas(array_filter($this->botones, fn (BotonOportunidad $b): bool => $this->puedeVer($usuario, $b->permiso)));
    }

    public function puedeVer(?User $usuario, ?string $permiso): bool
    {
        return $usuario !== null && ($permiso === null || $usuario->can($permiso));
    }

    /**
     * Si algún módulo registró la pestaña, la vea o no esta persona (plan 2.21b, D10: su capa reemplaza a la vacía del
     * despiece; quien no tiene su permiso no ve ninguna de las dos).
     */
    public function registrada(string $clave): bool
    {
        return isset($this->pestanas[$clave]);
    }

    public function pestanaPorNombre(string $nombre): ?PestanaOportunidad
    {
        foreach ($this->pestanas as $pestana) {
            if ($pestana->nombreLivewire() === $nombre) {
                return $pestana;
            }
        }

        return null;
    }

    /**
     * @template T of PestanaOportunidad|BotonOportunidad
     *
     * @param  array<string, T>  $elementos
     * @return list<T>
     */
    private static function ordenadas(array $elementos): array
    {
        $lista = array_values($elementos);
        usort($lista, fn (PestanaOportunidad|BotonOportunidad $a, PestanaOportunidad|BotonOportunidad $b): int => $a->orden <=> $b->orden);

        return $lista;
    }
}
