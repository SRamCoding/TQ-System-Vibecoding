<?php

declare(strict_types=1);

namespace App\Modules\Crm\Oportunidades\database\factories;

use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Nucleo\Models\LineaSolucion;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\OrigenComercial;
use App\Modules\Nucleo\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Solo `make()`: una oportunidad nace por `TransicionesService::crear` (le pone estado y número). La cuenta la pasa la
 * prueba (`cuenta_id`): Cuentas es otro módulo. Ver `Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba`.
 *
 * @extends Factory<Oportunidad>
 */
class OportunidadFactory extends Factory
{
    protected $model = Oportunidad::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Cableado estructurado de prueba',
            'monto_estimado' => '10000.00',
            'moneda_id' => fn (): int => Moneda::query()->where('codigo', 'PEN')->value('id') ?? Moneda::factory()->create(['codigo' => 'PEN'])->id,
            'fecha_cierre_estimada' => now()->addMonth()->toDateString(),
            'en_etapa_desde' => now()->toDateString(),
            'origen_comercial_id' => fn (): int => OrigenComercial::factory()->create()->id,
            'ejecutivo_user_id' => fn (): int => User::factory()->create()->id,
            'linea_solucion_id' => fn (): int => LineaSolucion::query()->value('id') ?? LineaSolucion::factory()->create()->id,
        ];
    }
}
