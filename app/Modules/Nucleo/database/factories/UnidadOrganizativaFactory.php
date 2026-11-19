<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Enums\TipoUnidadOrganizativa;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Un área inventada en la raíz del organigrama, sin jefatura definida.
 *
 * @extends Factory<UnidadOrganizativa>
 */
class UnidadOrganizativaFactory extends Factory
{
    protected $model = UnidadOrganizativa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'codigo' => fake()->unique()->bothify('UO-####??'),
            'nombre' => 'Área '.fake()->unique()->word().' '.fake()->numerify('###'),
            'tipo' => TipoUnidadOrganizativa::Area,
            'orden' => 0,
            'es_activa' => true,
        ];
    }

    public function dentroDe(UnidadOrganizativa $padre): static
    {
        return $this->state(['parent_id' => $padre->id]);
    }
}
