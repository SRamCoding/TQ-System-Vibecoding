<?php

declare(strict_types=1);

namespace App\Modules\Nucleo\database\factories;

use App\Modules\Nucleo\Models\CruceFunciones;
use App\Modules\Nucleo\Models\IncompatibilidadPermisos;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Un cruce abierto desde el 1 de enero de 2026, sin control.
 *
 * @extends Factory<CruceFunciones>
 */
class CruceFuncionesFactory extends Factory
{
    protected $model = CruceFunciones::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'incompatibilidad_id' => IncompatibilidadPermisos::factory(),
            'desde' => '2026-01-01',
            'hasta' => null,
        ];
    }
}
