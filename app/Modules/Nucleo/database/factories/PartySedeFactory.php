<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartySede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Una sede activa, sin coordenadas ni contacto, en una empresa nueva. Datos inventados (regla 10).
 *
 * @extends Factory<PartySede>
 */
class PartySedeFactory extends Factory
{
    protected $model = PartySede::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => fn (): int => Party::factory()->juridica()->create()->id,
            'nombre' => 'Almacén '.fake()->unique()->city(),
            'direccion' => fake()->streetAddress(),
            'es_activa' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(['es_activa' => false]);
    }
}
