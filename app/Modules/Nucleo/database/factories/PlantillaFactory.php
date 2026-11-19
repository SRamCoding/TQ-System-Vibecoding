<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\Plantilla;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Una plantilla de prueba de un tipo inventado (`pruebas.cosa`), personal de un usuario nuevo; `deLaEmpresa()` la deja
 * sin dueño. Sin versiones: las crea quien la usa.
 *
 * @extends Factory<Plantilla>
 */
class PlantillaFactory extends Factory
{
    protected $model = Plantilla::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => 'pruebas.cosa',
            'nombre' => 'Plantilla '.fake()->unique()->numerify('####'),
            'alcance' => Plantilla::PERSONAL,
            'propietario_user_id' => User::factory(),
            'es_activa' => true,
        ];
    }

    public function deLaEmpresa(): static
    {
        return $this->state(['alcance' => Plantilla::EMPRESA, 'propietario_user_id' => null]);
    }
}
