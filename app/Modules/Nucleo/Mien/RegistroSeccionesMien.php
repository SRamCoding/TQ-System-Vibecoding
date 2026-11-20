<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Mien;

use App\Modules\Nucleo\Models\User;
use Closure;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Secciones anchas de «Mien», debajo de las tarjetas (MV-017: Mien absorbe el tablero por rol). Cada módulo registra la
 * suya con una función que recibe a la persona y el rol elegido (`?rol=` en la URL) y devuelve su HTML, o null si a esa
 * persona no le toca.
 */
final class RegistroSeccionesMien
{
    /** @var array<string, Closure(User, ?string): ?Htmlable> */
    private array $secciones = [];

    /** @param  Closure(User, ?string): ?Htmlable  $dibujar */
    public function registrar(string $clave, Closure $dibujar): void
    {
        $this->secciones[$clave] = $dibujar;
    }

    /** @return list<Htmlable> */
    public function para(User $usuario, ?string $rol): array
    {
        return array_values(array_filter(array_map(fn (Closure $dibujar): ?Htmlable => $dibujar($usuario, $rol), $this->secciones)));
    }
}
