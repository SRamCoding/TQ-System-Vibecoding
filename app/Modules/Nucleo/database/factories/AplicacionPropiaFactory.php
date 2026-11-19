<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Enums\IconoAplicacion;
use App\Modules\Nucleo\Models\AplicacionPropia;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AplicacionPropia>
 */
class AplicacionPropiaFactory extends Factory
{
    protected $model = AplicacionPropia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nombre' => 'Propia '.fake()->unique()->numerify('####'),
            'enlace' => 'https://'.fake()->unique()->lexify('propia????').'.example',
            'icono' => IconoAplicacion::Generica,
        ];
    }
}
