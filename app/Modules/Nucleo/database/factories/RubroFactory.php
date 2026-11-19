<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\Rubro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rubro>
 */
class RubroFactory extends Factory
{
    protected $model = Rubro::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->randomElement(range('A', 'U')),
            'nombre' => fake()->words(2, true),
            'es_activo' => true,
        ];
    }
}
