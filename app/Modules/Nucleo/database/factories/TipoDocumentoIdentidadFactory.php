<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Códigos con letra de tres caracteres: no chocan con los de SUNAT que siembra CatalogosSeeder.
 *
 * @extends Factory<TipoDocumentoIdentidad>
 */
class TipoDocumentoIdentidadFactory extends Factory
{
    protected $model = TipoDocumentoIdentidad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('?##')),
            'nombre' => fake()->words(2, true),
            'longitud' => null,
            'es_activo' => true,
        ];
    }
}
