<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\CategoriaFrase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoriaFrase>
 */
class CategoriaFraseFactory extends Factory
{
    protected $model = CategoriaFrase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->lexify('cat_????'),
            'nombre' => fake()->words(2, true),
            'es_activa' => true,
        ];
    }
}
