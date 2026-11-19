<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Desarrollo;

use InvalidArgumentException;

/** Las piezas decorativas que el modo desarrollo deja cambiar (plan 1.4i, D3), en el orden en que se registraron. */
final class RegistroPiezasDecorativas
{
    /** @var array<string, PiezaDecorativa> */
    private array $piezas = [];

    public function registrar(PiezaDecorativa $pieza): void
    {
        if (preg_match('/^[a-z][a-z-]{0,39}$/', $pieza->clave()) !== 1) {
            throw new InvalidArgumentException("La clave de la pieza [{$pieza->clave()}] debe llevar solo minúsculas y guiones.");
        }

        if (isset($this->piezas[$pieza->clave()])) {
            throw new InvalidArgumentException("La pieza [{$pieza->clave()}] ya está registrada.");
        }

        $this->piezas[$pieza->clave()] = $pieza;
    }

    /** @return list<PiezaDecorativa> */
    public function todas(): array
    {
        return array_values($this->piezas);
    }

    public function pieza(string $clave): ?PiezaDecorativa
    {
        return $this->piezas[$clave] ?? null;
    }

    /** Si la pieza existe y ofrece ese valor: lo único que se guarda en la sesión. */
    public function ofrece(string $pieza, string $valor): bool
    {
        foreach ($this->pieza($pieza)?->opciones() ?? [] as $variantes) {
            if (array_key_exists($valor, $variantes)) {
                return true;
            }
        }

        return false;
    }
}
