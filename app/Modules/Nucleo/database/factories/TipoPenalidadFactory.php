<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\TipoPenalidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoPenalidad>
 */
class TipoPenalidadFactory extends Factory
{
    protected $model = TipoPenalidad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('???###')),
            'nombre' => fake()->words(2, true),
            'es_activo' => true,
        ];
    }
}
