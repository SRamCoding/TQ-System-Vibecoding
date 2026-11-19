<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\FormaPago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormaPago>
 */
class FormaPagoFactory extends Factory
{
    protected $model = FormaPago::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('???###')),
            'nombre' => fake()->words(2, true),
            'dias' => 30,
            'es_activa' => true,
        ];
    }
}
