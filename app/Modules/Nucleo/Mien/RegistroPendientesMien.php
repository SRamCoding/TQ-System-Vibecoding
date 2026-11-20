<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Mien;

use App\Modules\Nucleo\Models\User;
use Closure;

/**
 * MV-032: cuántas cosas esperan a la persona, para el número de la estrella de Mien. Cada módulo registra lo suyo con
 * una función que devuelve la cantidad y cómo se dice («esperan tu firma»), salida de lo mismo que sus tarjetas.
 */
final class RegistroPendientesMien
{
    /** @var array<string, Closure(User): array{cantidad: int, texto: string}> */
    private array $fuentes = [];

    /** @param  Closure(User): array{cantidad: int, texto: string}  $contar */
    public function registrar(string $clave, Closure $contar): void
    {
        $this->fuentes[$clave] = $contar;
    }

    /** @return array{total: int, resumen: string} */
    public function de(User $usuario): array
    {
        $total = 0;
        $partes = [];

        foreach ($this->fuentes as $contar) {
            $r = rescue(fn (): array => $contar($usuario), ['cantidad' => 0, 'texto' => ''], false);
            $total += $r['cantidad'];

            if ($r['cantidad'] > 0) {
                $partes[] = "{$r['cantidad']} {$r['texto']}";
            }
        }

        return ['total' => $total, 'resumen' => $total === 0 ? 'Todo al día' : ($total === 1 ? '1 pendiente' : "{$total} pendientes").' · '.implode(' · ', $partes)];
    }
}
