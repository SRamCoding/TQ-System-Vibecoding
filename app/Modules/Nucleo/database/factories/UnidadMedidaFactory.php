<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnidadMedida>
 */
class UnidadMedidaFactory extends Factory
{
    protected $model = UnidadMedida::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('???###')),
            'nombre' => fake()->word(),
            'abreviatura' => fake()->lexify('???'),
            'permite_decimales' => false,
            'es_activa' => true,
        ];
    }
}
