<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\TipoCambio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoCambio>
 */
class TipoCambioFactory extends Factory
{
    protected $model = TipoCambio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'moneda_id' => Moneda::factory(),
            'fecha' => fake()->unique()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'compra' => '3.7500',
            'venta' => '3.7600',
            'origen' => OrigenTipoCambio::Manual,
        ];
    }
}
