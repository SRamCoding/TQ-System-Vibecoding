<?php

declare(strict_types=1);

namespace App\Modules\Erp\Proyectos\Resumen;

use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use InvalidArgumentException;

/**
 * Las tarjetas del Resumen de la ficha (plan 2.61, D6). Cada módulo registra las suyas desde su provider; la ficha las
 * pide por sección. Las de costos solo se piden para quien tiene `campos.ver-costos` (D3): así el monto ni se calcula.
 */
final class RegistroTarjetasResumen
{
    /** @var array<string, TarjetaResumen> */
    private array $tarjetas = [];

    public function registrar(TarjetaResumen $tarjeta): void
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,39}$/', $tarjeta->clave()) !== 1) {
            throw new InvalidArgumentException("La clave de la tarjeta [{$tarjeta->clave()}] debe llevar solo minúsculas, cifras y guiones, hasta 40.");
        }

        if (isset($this->tarjetas[$tarjeta->clave()])) {
            throw new InvalidArgumentException("La tarjeta del Resumen [{$tarjeta->clave()}] ya está registrada.");
        }

        $this->tarjetas[$tarjeta->clave()] = $tarjeta;
    }

    /** @return list<TarjetaResumen> */
    public function tarjetas(): array
    {
        return array_values($this->tarjetas);
    }

    /**
     * Lo que dice cada sección para ese proyecto y esa persona, en el orden de las secciones; las tarjetas sin nada que
     * decir no van.
     *
     * @return array<string, list<ContenidoTarjeta>>
     */
    public function paraProyecto(int $proyectoId, User $usuario): array
    {
        $veCostos = $usuario->can(GrupoSensible::Costos->permiso());
        $lista = array_values(array_filter($this->tarjetas, fn (TarjetaResumen $t): bool => $veCostos || ! $t->exigeCostos()));
        usort($lista, fn (TarjetaResumen $a, TarjetaResumen $b): int => $a->orden() <=> $b->orden());
        $secciones = array_fill_keys(array_map(fn (SeccionResumen $s): string => $s->value, SeccionResumen::cases()), []);

        foreach ($lista as $tarjeta) {
            $contenido = $tarjeta->contenido($proyectoId, $usuario);

            if ($contenido !== null) {
                $secciones[$tarjeta->seccion()->value][] = $contenido;
            }
        }

        return $secciones;
    }
}
