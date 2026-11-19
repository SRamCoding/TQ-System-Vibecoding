<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\Cubso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cubso>
 */
class CubsoFactory extends Factory
{
    protected $model = Cubso::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->numerify('########'),
            'descripcion' => fake()->sentence(),
            'unidad_medida_id' => null,
            'version_catalogo' => null,
            'es_activo' => true,
        ];
    }
}
