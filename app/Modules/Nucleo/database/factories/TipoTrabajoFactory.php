<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\TipoTrabajo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoTrabajo>
 */
class TipoTrabajoFactory extends Factory
{
    protected $model = TipoTrabajo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('???###')),
            'nombre' => fake()->word(),
            'es_facturable_default' => true,
            'es_activo' => true,
        ];
    }
}
