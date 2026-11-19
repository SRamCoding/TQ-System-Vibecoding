<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Resumen;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use InvalidArgumentException;

/** Las opciones del filtro «Salud» de la portada (plan 2.61, D4), en el orden en que se registran. */
final class RegistroFiltrosSalud
{
    /** @var array<string, FiltroSalud> */
    private array $filtros = [];

    public function registrar(FiltroSalud $filtro): void
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,39}$/', $filtro->clave()) !== 1 || isset($this->filtros[$filtro->clave()])) {
            throw new InvalidArgumentException("El filtro de salud [{$filtro->clave()}] no tiene una clave válida o ya está registrado.");
        }

        $this->filtros[$filtro->clave()] = $filtro;
    }

    /** @return array<string, FiltroSalud> los que la persona puede usar */
    public function para(?User $usuario): array
    {
        $veCostos = $usuario?->can(GrupoSensible::Costos->permiso()) ?? false;

        return array_filter($this->filtros, fn (FiltroSalud $f): bool => ($veCostos || ! $f->exigeCostos())
            && ($f->permiso() === null || ($usuario?->can($f->permiso()) ?? false)));
    }
}
