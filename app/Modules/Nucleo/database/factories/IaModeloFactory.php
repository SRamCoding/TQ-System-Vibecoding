<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\IaModelo;
use App\Modules\Nucleo\Models\IaProveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IaModelo>
 */
class IaModeloFactory extends Factory
{
    protected $model = IaModelo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proveedor_id' => IaProveedor::factory(),
            'codigo' => fake()->unique()->lexify('modelo-????'),
            'nombre' => 'Modelo '.fake()->unique()->numerify('####'),
            'max_tokens_salida' => 4096,
            'es_por_defecto' => false,
            'es_activo' => true,
        ];
    }
}
