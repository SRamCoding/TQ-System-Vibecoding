<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Preferencias;

use LogicException;

/**
 * Las preferencias por usuario que declaran los módulos (plan 1.4g, D1). Cada módulo trae las suyas desde su provider,
 * como RegistroParametros; los valores de cada persona viven en `preferencias_usuario` (o en la tabla de su lista) y
 * solo los lee y escribe PreferenciasUsuarioService. Una preferencia nueva se declara aquí: no se crea una columna ni
 * un método suelto.
 */
final class RegistroPreferencias
{
    /** @var array<string, DefinicionPreferencia> */
    private array $definiciones = [];

    public function declarar(DefinicionPreferencia ...$definiciones): void
    {
        foreach ($definiciones as $definicion) {
            if (isset($this->definiciones[$definicion->clave()])) {
                throw new LogicException("La preferencia «{$definicion->clave()}» ya está declarada.");
            }

            $this->definiciones[$definicion->clave()] = $definicion;
        }
    }

    /** La definición de `grupo.nombre`, o nula si ningún módulo la declaró. */
    public function definicion(string $clave): ?DefinicionPreferencia
    {
        return $this->definiciones[$clave] ?? null;
    }

    /**
     * @return array<string, DefinicionPreferencia>
     */
    public function todas(): array
    {
        return $this->definiciones;
    }

    /**
     * Las del grupo, en el orden en que se declararon.
     *
     * @return list<DefinicionPreferencia>
     */
    public function delGrupo(GrupoPreferencia $grupo): array
    {
        return array_values(array_filter($this->definiciones, fn (DefinicionPreferencia $definicion): bool => $definicion->grupo === $grupo));
    }
}
