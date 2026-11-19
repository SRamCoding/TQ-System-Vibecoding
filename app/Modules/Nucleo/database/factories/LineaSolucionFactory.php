<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\LineaSolucion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LineaSolucion>
 */
class LineaSolucionFactory extends Factory
{
    protected $model = LineaSolucion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('???###')),
            'nombre' => fake()->words(2, true),
            'es_activa' => true,
        ];
    }
}
