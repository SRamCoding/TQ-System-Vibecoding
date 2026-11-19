<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\TipoAdjunto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoAdjunto>
 */
class TipoAdjuntoFactory extends Factory
{
    protected $model = TipoAdjunto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->lexify('tipo_????'),
            'nombre' => 'Tipo '.fake()->unique()->numerify('####'),
            'es_obligatorio' => false,
            'es_activo' => true,
        ];
    }
}
