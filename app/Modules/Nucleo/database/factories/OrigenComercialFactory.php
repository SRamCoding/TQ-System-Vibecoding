<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\OrigenComercial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrigenComercial>
 */
class OrigenComercialFactory extends Factory
{
    protected $model = OrigenComercial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('ORIGEN_###')),
            'nombre' => fake()->words(2, true),
            'es_activo' => true,
        ];
    }
}
