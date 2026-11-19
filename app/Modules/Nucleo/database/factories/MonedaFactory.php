<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\Moneda;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Moneda>
 */
class MonedaFactory extends Factory
{
    protected $model = Moneda::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->lexify('???')),
            'nombre' => fake()->words(2, true),
            'simbolo' => fake()->lexify('?'),
            'decimales' => 2,
            'es_activa' => true,
        ];
    }
}
