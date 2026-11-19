<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Navegacion;

/**
 * Las cajas de los módulos. Cada módulo registra la suya desde su provider. Quién ve cuáles, en el inicio y en la barra
 * lateral, lo decide NavegacionService (plan 1.4f, D3).
 */
final class RegistroModulos
{
    /** @var array<string, CajaModulo> */
    private array $cajas = [];

    public function registrar(CajaModulo $caja): void
    {
        $this->cajas[$caja->clave] = $caja;
    }

    /**
     * Todas las cajas registradas, en el orden en que se registraron.
     *
     * @return list<CajaModulo>
     */
    public function todas(): array
    {
        return array_values($this->cajas);
    }
}
