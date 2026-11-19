<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\CentroCosto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CentroCosto>
 */
class CentroCostoFactory extends Factory
{
    protected $model = CentroCosto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('CC###')),
            'nombre' => 'Centro '.fake()->unique()->numerify('####'),
            'es_activo' => true,
        ];
    }
}
