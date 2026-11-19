<?php

declare(strict_types=1);

namespace App\Modules\Crm\Catalogo\database\factories;

use App\Modules\Crm\Catalogo\Models\Producto;
use App\Modules\Nucleo\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Productos inventados para pruebas (plan 2.60, D1; regla 10). Toma la primera unidad de medida activa del catálogo
 * del núcleo (`NucleoSeeder`); sin ella, crea una.
 *
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => strtoupper(fake()->unique()->bothify('PRD-####')),
            'descripcion' => 'Producto de prueba '.fake()->unique()->numerify('####'),
            'descripcion_corta' => null,
            'unidad_medida_id' => fn (): int => UnidadMedida::query()->where('es_activa', true)->orderBy('id')->value('id')
                ?? UnidadMedida::factory()->create()->id,
            'requiere_serie' => false,
            'es_servicio' => false,
            'es_activo' => true,
        ];
    }

    public function conSerie(): static
    {
        return $this->state(['requiere_serie' => true]);
    }

    public function servicio(): static
    {
        return $this->state(['es_servicio' => true]);
    }

    public function descontinuado(): static
    {
        return $this->state(['es_activo' => false]);
    }
}
