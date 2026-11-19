<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\PermisoPorAmbito;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

/**
 * Un rol nuevo dado a un usuario en una unidad organizativa nueva, sin límite de fechas.
 *
 * @extends Factory<PermisoPorAmbito>
 */
class PermisoPorAmbitoFactory extends Factory
{
    protected $model = PermisoPorAmbito::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'rol_id' => fn (): int => (int) Role::findOrCreate('rol-'.fake()->unique()->numerify('#####'))->getKey(),
            'ambito_type' => 'unidad_organizativa',
            'ambito_id' => UnidadOrganizativa::factory(),
            'desde' => null,
            'hasta' => null,
        ];
    }
}
