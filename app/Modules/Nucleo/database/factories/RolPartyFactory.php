<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\RolParty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RolParty>
 */
class RolPartyFactory extends Factory
{
    protected $model = RolParty::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('rol_????##'),
            'nombre' => fake()->words(2, true),
            'descripcion' => null,
            'es_activo' => true,
        ];
    }
}
