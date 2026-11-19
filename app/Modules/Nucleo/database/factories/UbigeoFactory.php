<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Enums\OrigenUbigeo;
use App\Modules\Nucleo\Models\Ubigeo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ubigeo>
 */
class UbigeoFactory extends Factory
{
    protected $model = Ubigeo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->numerify('######'),
            'departamento' => fake()->city(),
            'provincia' => fake()->city(),
            'distrito' => fake()->city(),
            'origen' => OrigenUbigeo::Inei,
            'es_activo' => true,
        ];
    }
}
