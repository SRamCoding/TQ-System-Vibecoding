<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Un puesto inventado en una unidad nueva.
 *
 * @extends Factory<Puesto>
 */
class PuestoFactory extends Factory
{
    protected $model = Puesto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Puesto '.fake()->unique()->numerify('####'),
            'unidad_organizativa_id' => UnidadOrganizativa::factory(),
            'es_activo' => true,
        ];
    }
}
