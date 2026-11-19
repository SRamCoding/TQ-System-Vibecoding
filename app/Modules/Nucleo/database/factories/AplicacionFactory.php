<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Enums\IconoAplicacion;
use App\Modules\Nucleo\Models\Aplicacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Aplicacion>
 */
class AplicacionFactory extends Factory
{
    protected $model = Aplicacion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->lexify('app_????'),
            'nombre' => 'Aplicación '.fake()->unique()->numerify('####'),
            'enlace' => 'https://'.fake()->unique()->lexify('app????').'.example',
            'icono' => IconoAplicacion::Generica,
            'orden' => 100,
            'es_activo' => true,
        ];
    }
}
