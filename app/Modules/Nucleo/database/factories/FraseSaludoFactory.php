<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Enums\IdiomaSaludo;
use App\Modules\Nucleo\Enums\TipoFraseSaludo;
use App\Modules\Nucleo\Models\CategoriaFrase;
use App\Modules\Nucleo\Models\FraseSaludo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Por defecto, una frase sin condiciones en español. `saludo()` da un saludo con `{nombre}` y sin categoría.
 *
 * @extends Factory<FraseSaludo>
 */
class FraseSaludoFactory extends Factory
{
    protected $model = FraseSaludo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->lexify('frase-????????'),
            'tipo' => TipoFraseSaludo::Frase,
            'categoria_frase_id' => CategoriaFrase::factory(),
            'idioma' => IdiomaSaludo::Espanol,
            'texto' => fake()->sentence(),
            'es_activa' => true,
        ];
    }

    public function saludo(): static
    {
        return $this->state(fn (): array => [
            'codigo' => fake()->unique()->lexify('saludo-????????'),
            'tipo' => TipoFraseSaludo::Saludo,
            'categoria_frase_id' => null,
            'texto' => 'Hola, {nombre}',
        ]);
    }
}
