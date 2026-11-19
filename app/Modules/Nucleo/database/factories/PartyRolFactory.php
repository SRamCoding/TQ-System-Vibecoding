<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyRol;
use App\Modules\Nucleo\Models\RolParty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Un periodo abierto desde el 1 de enero de 2026 en un rol nuevo.
 *
 * @extends Factory<PartyRol>
 */
class PartyRolFactory extends Factory
{
    protected $model = PartyRol::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'rol_party_id' => RolParty::factory(),
            'desde' => '2026-01-01',
            'hasta' => null,
        ];
    }
}
