<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyCuentaBancaria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Una cuenta normal, activa y no principal, de una empresa nueva en un banco nuevo. El banco no lleva el rol
 * `banco`: eso lo revisa el servicio, no la base. Datos inventados (regla 10).
 *
 * @extends Factory<PartyCuentaBancaria>
 */
class PartyCuentaBancariaFactory extends Factory
{
    protected $model = PartyCuentaBancaria::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => fn (): int => Party::factory()->juridica()->create()->id,
            'banco_party_id' => fn (): int => Party::factory()->juridica()->create()->id,
            'numero_cuenta' => fake()->unique()->numerify('#############'),
            'moneda_id' => fn (): int => Moneda::query()->firstOrCreate(['codigo' => 'PEN'], ['nombre' => 'Sol', 'simbolo' => 'S/', 'decimales' => 2])->id,
            'es_detraccion' => false,
            'es_principal' => false,
            'es_activa' => true,
        ];
    }

    public function principal(): static
    {
        return $this->state(['es_principal' => true]);
    }
}
