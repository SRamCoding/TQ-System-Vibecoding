<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\PartyRedSocial;
use App\Modules\Nucleo\Models\RedSocial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyRedSocial>
 */
class PartyRedSocialFactory extends Factory
{
    protected $model = PartyRedSocial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory(),
            'red_social_id' => RedSocial::factory(),
            // Del primer dominio de la red; el sitio web, de cualquiera.
            'enlace' => fn (array $atributos): string => 'https://'.explode(',', RedSocial::query()->findOrFail($atributos['red_social_id'])->dominios ?? 'example.com')[0].'/'.fake()->unique()->lexify('????????'),
            'es_privada' => false,
        ];
    }
}
