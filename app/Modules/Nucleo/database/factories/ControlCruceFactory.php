<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\ControlCruce;
use App\Modules\Nucleo\Models\CruceFunciones;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * El control actual de un cruce nuevo, sin vencimiento, a cargo de otra persona.
 *
 * @extends Factory<ControlCruce>
 */
class ControlCruceFactory extends Factory
{
    protected $model = ControlCruce::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cruce_id' => CruceFunciones::factory(),
            'user_id' => fn (array $atributos): int => (int) CruceFunciones::query()->whereKey($atributos['cruce_id'])->value('user_id'),
            'control' => 'Tesorería revisa cada semana los pagos que registró y aprobó la misma persona.',
            'responsable_user_id' => User::factory(),
            'vigente_hasta' => null,
            'es_actual' => true,
        ];
    }
}
