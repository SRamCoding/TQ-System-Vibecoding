<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\TipoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoDocumento>
 */
class TipoDocumentoFactory extends Factory
{
    protected $model = TipoDocumento::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->numerify('9##'),
            'nombre' => fake()->words(2, true),
            'es_electronico' => true,
            'afecta_cuenta_corriente' => true,
            'signo' => 1,
            'es_activo' => true,
        ];
    }
}
