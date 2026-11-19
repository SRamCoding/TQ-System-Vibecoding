<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\IncompatibilidadPermisos;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Permission;

/**
 * Un par de permisos nuevos, ya ordenados (`permiso_a_id < permiso_b_id`).
 *
 * @extends Factory<IncompatibilidadPermisos>
 */
class IncompatibilidadPermisosFactory extends Factory
{
    protected $model = IncompatibilidadPermisos::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'permiso_a_id' => fn (): int => (int) Permission::findOrCreate('prueba.a-'.fake()->unique()->numerify('#####'))->getKey(),
            'permiso_b_id' => fn (): int => (int) Permission::findOrCreate('prueba.b-'.fake()->unique()->numerify('#####'))->getKey(),
            'motivo' => 'Quien registra no aprueba lo mismo.',
            'es_activa' => true,
        ];
    }

    public function entre(Permission $uno, Permission $otro): static
    {
        $ids = [(int) $uno->getKey(), (int) $otro->getKey()];
        sort($ids);

        return $this->state(['permiso_a_id' => $ids[0], 'permiso_b_id' => $ids[1]]);
    }
}
