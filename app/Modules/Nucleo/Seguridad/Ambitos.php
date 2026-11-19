<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\Seguridad;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Dónde tiene una persona un permiso (plan 1.4, D7): en todos los ámbitos, porque se lo da un rol global, o solo en
 * algunos. Sirve para filtrar una lista en la base con un `whereIn`, no registro por registro.
 */
final readonly class Ambitos
{
    /**
     * @param  list<int>  $ids
     */
    private function __construct(public bool $todos, public array $ids) {}

    public static function todos(): self
    {
        return new self(true, []);
    }

    /**
     * @param  list<int>  $ids
     */
    public static function solo(array $ids): self
    {
        return new self(false, array_values(array_unique($ids)));
    }

    public function incluye(int $id): bool
    {
        return $this->todos || in_array($id, $this->ids, true);
    }

    /**
     * Deja en la consulta solo los registros de estos ámbitos; si son todos, no la toca.
     *
     * @template TModelo of Model
     *
     * @param  Builder<TModelo>  $consulta
     * @return Builder<TModelo>
     */
    public function filtrar(Builder $consulta, string $columna): Builder
    {
        return $this->todos ? $consulta : $consulta->whereIn($columna, $this->ids);
    }
}
