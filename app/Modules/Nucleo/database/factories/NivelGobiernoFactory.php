<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\NivelGobierno;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NivelGobierno>
 */
class NivelGobiernoFactory extends Factory
{
    protected $model = NivelGobierno::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('NIVEL_###')),
            'nombre' => fake()->words(2, true),
            'es_activo' => true,
        ];
    }
}
