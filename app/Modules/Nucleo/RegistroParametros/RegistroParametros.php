<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\RegistroParametros;

use App\Modules\Nucleo\Excepciones\ParametroDuplicado;

/**
 * Parámetros que declaran los módulos (plan 1.2). Cada módulo trae los suyos desde su provider; el
 * seeder del núcleo crea los que faltan y nunca pisa un valor ya cambiado. Es código, como
 * RegistroAcciones: los valores viven en la tabla.
 */
final class RegistroParametros
{
    /** @var array<string, DefinicionParametro> */
    private array $definiciones = [];

    public function declarar(DefinicionParametro ...$definiciones): void
    {
        foreach ($definiciones as $definicion) {
            $existente = $this->definiciones[$definicion->nombre()] ?? null;

            if ($existente !== null && ! $existente->igualA($definicion)) {
                throw ParametroDuplicado::nombre($definicion->nombre());
            }

            $this->definiciones[$definicion->nombre()] = $definicion;
        }
    }

    /** La definición de `grupo.clave`, o nula si ningún módulo lo declaró. */
    public function definicion(string $nombre): ?DefinicionParametro
    {
        return $this->definiciones[$nombre] ?? null;
    }

    /**
     * @return array<string, DefinicionParametro>
     */
    public function todas(): array
    {
        return $this->definiciones;
    }
}
