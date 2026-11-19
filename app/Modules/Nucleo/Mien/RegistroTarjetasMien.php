<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Mien;

use App\Modules\Nucleo\Models\User;
use InvalidArgumentException;
use Livewire\Livewire;

/**
 * Las tarjetas de «Mien» (plan 1.4l, D3), como RegistroModulos: cada módulo registra las suyas desde su provider.
 * Cada persona ve solo las de los módulos en que tiene permiso.
 */
final class RegistroTarjetasMien
{
    /** @var array<string, TarjetaMien> */
    private array $tarjetas = [];

    public function registrar(TarjetaMien $tarjeta): void
    {
        if (isset($this->tarjetas[$tarjeta->clave])) {
            throw new InvalidArgumentException("La tarjeta [{$tarjeta->clave}] ya está registrada.");
        }

        $this->tarjetas[$tarjeta->clave] = $tarjeta;
        // Con nombre propio: la carga diferida vuelve a pedir el componente por su nombre.
        Livewire::component($tarjeta->nombreLivewire(), $tarjeta->componente);
    }

    /**
     * Las que la persona puede ver, en su orden.
     *
     * @return list<TarjetaMien>
     */
    public function para(User $usuario): array
    {
        $visibles = array_values(array_filter($this->tarjetas, fn (TarjetaMien $tarjeta): bool => $this->puedeVer($usuario, $tarjeta)));
        // usort es estable: a igual orden, queda el de registro.
        usort($visibles, fn (TarjetaMien $a, TarjetaMien $b): int => $a->orden <=> $b->orden);

        return $visibles;
    }

    public function puedeVer(User $usuario, TarjetaMien $tarjeta): bool
    {
        return $tarjeta->permiso === null || $usuario->can($tarjeta->permiso);
    }

    /** La tarjeta de un componente, por su nombre de Livewire (`mien.<clave>`). */
    public function porNombre(string $nombre): ?TarjetaMien
    {
        foreach ($this->tarjetas as $tarjeta) {
            if ($tarjeta->nombreLivewire() === $nombre) {
                return $tarjeta;
            }
        }

        return null;
    }
}
