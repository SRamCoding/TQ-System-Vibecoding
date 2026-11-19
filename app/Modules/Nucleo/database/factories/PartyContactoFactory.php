<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyContacto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Un vínculo vigente, sin fechas, en una empresa nueva. Datos inventados (regla 10).
 *
 * @extends Factory<PartyContacto>
 */
class PartyContactoFactory extends Factory
{
    protected $model = PartyContacto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => fn (): int => Party::factory()->juridica()->create()->id,
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'cargo' => fake()->jobTitle(),
            'telefono' => fake()->numerify('9## ### ###'),
            'email' => fake()->unique()->safeEmail(),
            'es_decisor' => false,
            'es_principal' => false,
        ];
    }

    public function principal(): static
    {
        return $this->state(['es_principal' => true]);
    }

    public function cerrado(string $hasta = '2026-07-31'): static
    {
        return $this->state(['hasta' => $hasta, 'es_principal' => false]);
    }
}
