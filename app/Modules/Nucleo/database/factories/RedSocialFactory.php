<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Enums\IconoRed;
use App\Modules\Nucleo\Models\RedSocial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RedSocial>
 */
class RedSocialFactory extends Factory
{
    protected $model = RedSocial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->lexify('red_????'),
            'nombre' => 'Red '.fake()->unique()->numerify('####'),
            'dominios' => fake()->unique()->lexify('red????').'.example',
            'icono' => IconoRed::Enlace,
            'es_activo' => true,
        ];
    }
}
