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
    /**
     * Cada fuente devuelve su cantidad y cómo se dice y, para el globo de Mien en la barra lateral (MV-052), sus grupos
     * con cada pendiente y adónde lleva: `grupos` => list<{titulo, items: list<{texto, nota?, url, tono?}>}>.
     *
     * @var array<string, Closure(User): array<string, mixed>>
     */
    private array $fuentes = [];

    /** @param  Closure(User): array{cantidad: int, texto: string}  $contar */
    public function registrar(string $clave, Closure $contar): void
    {
        $this->fuentes[$clave] = $contar;
    }

    /**
     * MV-052: todo lo pendiente de la persona, agrupado, sin omitir ninguno.
     *
     * @return list<array{titulo: string, items: list<array{texto: string, nota: ?string, url: string, tono: ?string}>}>
     */
    public function detalle(User $usuario): array
    {
        $grupos = [];

        foreach ($this->fuentes as $contar) {
            $r = rescue(fn (): array => $contar($usuario), [], false);

            foreach ($r['grupos'] ?? [] as $g) {
                if (($g['items'] ?? []) !== []) {
                    $grupos[] = ['titulo' => (string) $g['titulo'], 'items' => array_map(fn (array $i): array => ['texto' => (string) $i['texto'], 'nota' => $i['nota'] ?? null, 'url' => (string) $i['url'], 'tono' => $i['tono'] ?? null], $g['items'])];
                }
            }
        }

        return $grupos;
    }

    /** @return array{total: int, resumen: string} */
    public function de(User $usuario): array
    {
        $total = 0;
        $partes = [];

        foreach ($this->fuentes as $contar) {
            $r = rescue(fn (): array => $contar($usuario), ['cantidad' => 0, 'texto' => ''], false);
            $r['cantidad'] ??= 0;
            $total += $r['cantidad'];

            if ($r['cantidad'] > 0) {
                $partes[] = "{$r['cantidad']} {$r['texto']}";
            }
        }

        return ['total' => $total, 'resumen' => $total === 0 ? 'Todo al día' : ($total === 1 ? '1 pendiente' : "{$total} pendientes").' · '.implode(' · ', $partes)];
    }
}
