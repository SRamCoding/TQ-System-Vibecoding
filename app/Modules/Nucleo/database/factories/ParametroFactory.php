<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Enums\TipoParametro;
use App\Modules\Nucleo\Models\Parametro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Parametro>
 */
class ParametroFactory extends Factory
{
    protected $model = Parametro::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grupo' => 'pruebas',
            'clave' => fake()->unique()->lexify('clave_????????'),
            'valor' => 'valor de prueba',
            'tipo' => TipoParametro::Texto,
            'descripcion' => fake()->sentence(),
            'es_editable' => true,
        ];
    }

    public function decimal(string $valor = '18.00'): static
    {
        return $this->state(['tipo' => TipoParametro::Decimal, 'valor' => $valor]);
    }

    public function entero(int $valor = 30): static
    {
        return $this->state(['tipo' => TipoParametro::Entero, 'valor' => (string) $valor]);
    }

    public function noEditable(): static
    {
        return $this->state(['es_editable' => false]);
    }
}
