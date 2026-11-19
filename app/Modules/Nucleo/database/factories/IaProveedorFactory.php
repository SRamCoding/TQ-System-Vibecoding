<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\IaProveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IaProveedor>
 */
class IaProveedorFactory extends Factory
{
    protected $model = IaProveedor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $codigo = fake()->unique()->lexify('prov_????');

        return [
            'codigo' => $codigo,
            'nombre' => 'Proveedor '.fake()->unique()->numerify('####'),
            'implementacion' => 'falso',
            'es_externo' => true,
            'variable_clave' => 'IA_CLAVE_'.strtoupper($codigo),
            'llamadas_en_paralelo' => 2,
            'es_activo' => true,
        ];
    }

    public function local(): static
    {
        return $this->state(['es_externo' => false, 'variable_clave' => null]);
    }
}
