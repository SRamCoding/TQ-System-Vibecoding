<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\Feriado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feriado>
 */
class FeriadoFactory extends Factory
{
    protected $model = Feriado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->dateTimeBetween('2030-01-01', '2039-12-31')->format('Y-m-d'),
            'nombre' => 'Feriado de prueba',
            'tipo' => 'feriado_nacional',
            'techquk_no_trabaja' => true,
            'es_activo' => true,
        ];
    }
}
