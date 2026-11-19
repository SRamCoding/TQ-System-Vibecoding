<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\Secciones;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use InvalidArgumentException;

/**
 * El registro de secciones de los formularios de la oportunidad (plan 2.21, D12.4). Las acciones de crear y avanzar
 * suman sus campos (con la clave de la sección delante, para que no choquen) y, dentro de su transacción, le pasan a
 * cada sección lo suyo.
 */
final class SeccionesDeOportunidad
{
    private const string SEPARADOR = '__';

    /** @var array<string, SeccionDeOportunidad> */
    private array $secciones = [];

    public function registrar(SeccionDeOportunidad $seccion): void
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $seccion->clave()) !== 1 || str_contains($seccion->clave(), self::SEPARADOR)) {
            throw new InvalidArgumentException("La clave de sección «{$seccion->clave()}» va en minúsculas, con guion bajo simple.");
        }

        if (isset($this->secciones[$seccion->clave()])) {
            throw new InvalidArgumentException("La sección «{$seccion->clave()}» ya está registrada.");
        }

        $this->secciones[$seccion->clave()] = $seccion;
    }

    /** @return list<SeccionDeOportunidad> */
    public function para(string $formulario): array
    {
        return array_values(array_filter($this->secciones, fn (SeccionDeOportunidad $s): bool => in_array($formulario, $s->formularios(), true)));
    }

    /**
     * Los campos de todas las secciones del formulario, con la clave delante: los suma la acción a los suyos.
     *
     * @return list<Campo>
     */
    public function campos(string $formulario): array
    {
        $campos = [];

        foreach ($this->para($formulario) as $seccion) {
            foreach ($seccion->campos() as $campo) {
                $campos[] = $campo->renombrado($seccion->clave().self::SEPARADOR.$campo->nombre);
            }
        }

        return $campos;
    }

    /**
     * Le pasa a cada sección lo suyo, con los nombres cortos. Se llama dentro de la transacción que crea o avanza.
     *
     * @param  array<string, mixed>  $datos  los de la acción, ya validados
     */
    public function guardar(int $oportunidadId, string $formulario, array $datos, User $usuario): void
    {
        foreach ($this->para($formulario) as $seccion) {
            $prefijo = $seccion->clave().self::SEPARADOR;
            $suyos = [];

            foreach ($datos as $nombre => $valor) {
                if (str_starts_with((string) $nombre, $prefijo)) {
                    $suyos[substr((string) $nombre, strlen($prefijo))] = $valor;
                }
            }

            $seccion->guardar($oportunidadId, $formulario, $suyos, $usuario);
        }
    }
}
