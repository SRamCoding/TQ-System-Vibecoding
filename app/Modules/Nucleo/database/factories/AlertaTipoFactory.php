<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\AlertaTipo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertaTipo>
 */
class AlertaTipoFactory extends Factory
{
    protected $model = AlertaTipo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'prueba.alerta-'.fake()->unique()->numerify('####'),
            'es_activa' => true,
        ];
    }
}
