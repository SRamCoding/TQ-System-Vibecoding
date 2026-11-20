<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Condiciones;

use Closure;

/**
 * MVP rápido (ficha t9-cierre, flujo 6): la lista de verificación del cierre. Cada módulo registra lo suyo (órdenes
 * abiertas, tareo sin cerrar…) sin que Proyectos conozca sus modelos. Lo que bloquea impide cerrar; lo demás se avisa.
 */
final class VerificacionesCierre
{
    /** @var array<string, Closure(int): array{que: string, detalle: string, resuelto: bool, bloquea: bool}> */
    private array $verificaciones = [];

    /** @param Closure(int): array{que: string, detalle: string, resuelto: bool, bloquea: bool} $verificacion */
    public function registrar(string $clave, Closure $verificacion): void
    {
        $this->verificaciones[$clave] = $verificacion;
    }

    /** @return list<array{que: string, detalle: string, resuelto: bool, bloquea: bool}> */
    public function de(int $proyectoId): array
    {
        return array_values(array_map(fn (Closure $v): array => $v($proyectoId), $this->verificaciones));
    }

    /** @return list<string> lo que impide cerrar */
    public function bloqueos(int $proyectoId): array
    {
        return array_values(array_map(fn (array $v): string => "{$v['que']} ({$v['detalle']})", array_filter($this->de($proyectoId), fn (array $v): bool => $v['bloquea'] && ! $v['resuelto'])));
    }
}
