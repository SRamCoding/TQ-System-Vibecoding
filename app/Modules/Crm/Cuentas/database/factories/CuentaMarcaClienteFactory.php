<?php

declare(strict_types=1);

namespace App\Modules\Crm\Cuentas\database\factories;

use App\Modules\Crm\Cuentas\Enums\OrigenMarcaCliente;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Models\CuentaMarcaCliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Una marca manual de cliente. Solo para pruebas de la base: `cliente_desde` lo recalcula CuentasService, no la factory.
 *
 * @extends Factory<CuentaMarcaCliente>
 */
class CuentaMarcaClienteFactory extends Factory
{
    protected $model = CuentaMarcaCliente::class;

    public function definition(): array
    {
        return [
            'cuenta_id' => fn (): int => Cuenta::factory()->create()->id,
            'origen' => OrigenMarcaCliente::Manual,
            'oportunidad_id' => null,
            'fecha' => '2026-01-15',
            'comentario' => 'Cliente desde antes del sistema',
        ];
    }

    public function deOportunidad(int $oportunidadId): static
    {
        return $this->state(['origen' => OrigenMarcaCliente::Oportunidad, 'oportunidad_id' => $oportunidadId, 'comentario' => null]);
    }
}
