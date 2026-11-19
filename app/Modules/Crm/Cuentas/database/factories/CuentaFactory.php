<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\database\factories;

use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Nucleo\Models\OrigenComercial;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Una cuenta privada y prospecto, de una empresa inventada. Para pruebas de lectura y de la base: las reglas que no
 * revisa la base (ejecutivo con permiso, nivel solo en públicas) las pone CuentasService, no la factory.
 *
 * @extends Factory<Cuenta>
 */
class CuentaFactory extends Factory
{
    protected $model = Cuenta::class;

    public function definition(): array
    {
        return [
            'party_id' => fn (): int => Party::factory()->juridica()->create()->id,
            'ejecutivo_user_id' => fn (): int => User::factory()->create()->id,
            'origen_comercial_id' => fn (): int => OrigenComercial::factory()->create()->id,
            'nivel_gobierno_id' => null,
            'cliente_desde' => null,
            'es_activa' => true,
        ];
    }
}
