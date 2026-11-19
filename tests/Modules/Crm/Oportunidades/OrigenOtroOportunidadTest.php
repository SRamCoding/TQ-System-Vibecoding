<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Nucleo\database\seeders\CatalogosSeeder;
use App\Modules\Nucleo\database\seeders\EstadosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Excepciones\DetalleDeOtroRechazado;
use App\Modules\Nucleo\Models\LineaSolucion;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\OrigenComercial;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * El origen «Otro» de una oportunidad (plan 2.0, paso 6, D5): la hereda de su cuenta con su detalle, o se elige y
 * entonces pide cuál es. Por la acción registrada. Datos inventados.
 */

beforeEach(function () {
    $this->seed([ParametrosSeeder::class, CatalogosSeeder::class, EstadosSeeder::class, PermisosSeeder::class]);
    OportunidadesDePrueba::sinExigirCotizacion();
    $this->ejecutivo = User::factory()->create()->givePermissionTo(CuentasService::PERMISO_ATENDER);
    $this->gerencia = User::factory()->create()->givePermissionTo(['crm.oportunidades.crear', 'crm.oportunidades.editar']);
    $this->otro = (int) OrigenComercial::query()->where('codigo', 'OTRO')->value('id');
});

function ooCrear(int $cuentaId, User $quien, array $mas = []): mixed
{
    return app(EjecutorAcciones::class)->ejecutar('crm.oportunidades.crear', [
        'cuenta_id' => $cuentaId, 'nombre' => 'Cableado del colegio', 'monto_estimado' => '1500.00',
        'moneda_id' => Moneda::query()->where('codigo', 'PEN')->value('id'), 'fecha_cierre_estimada' => '2026-12-15',
        'linea_solucion_id' => LineaSolucion::query()->value('id') ?? LineaSolucion::factory()->create()->id, ...$mas,
    ], $quien);
}

test('sin origen propio, hereda el «Otro» de la cuenta con su detalle', function () {
    $cuenta = Cuenta::factory()->create(['ejecutivo_user_id' => $this->ejecutivo->id, 'origen_comercial_id' => $this->otro, 'origen_detalle' => 'Aviso en la radio local']);

    $creada = ooCrear($cuenta->id, $this->gerencia);

    expect(Oportunidad::query()->findOrFail($creada->id)->only(['origen_comercial_id', 'origen_detalle']))
        ->toBe(['origen_comercial_id' => $this->otro, 'origen_detalle' => 'Aviso en la radio local']);
});

test('eligiendo «Otro», pide cuál es', function () {
    $cuenta = Cuenta::factory()->create(['ejecutivo_user_id' => $this->ejecutivo->id]);

    expect(fn () => ooCrear($cuenta->id, $this->gerencia, ['origen_comercial_id' => $this->otro]))->toThrow(DetalleDeOtroRechazado::class)
        ->and(Oportunidad::query()->findOrFail(ooCrear($cuenta->id, $this->gerencia, ['origen_comercial_id' => $this->otro, 'origen_detalle' => 'Feria del colegio'])->id)->origen_detalle)->toBe('Feria del colegio');
});

function ooEditar(int $id, User $quien, array $mas = []): mixed
{
    $o = Oportunidad::query()->findOrFail($id);

    return app(EjecutorAcciones::class)->ejecutar('crm.oportunidades.editar', [
        'oportunidad_id' => $id, 'version' => $o->version, 'nombre' => $o->nombre, 'monto_estimado' => '1500.00',
        'moneda_id' => $o->moneda_id, 'fecha_cierre_estimada' => '2026-12-15', 'linea_solucion_id' => $o->linea_solucion_id, ...$mas,
    ], $quien);
}

test('al editar: sin origen conserva origen y detalle; a uno de la lista borra el detalle; corto o sin «Otro», se rechaza', function () {
    $cuenta = Cuenta::factory()->create(['ejecutivo_user_id' => $this->ejecutivo->id]);
    $feria = (int) OrigenComercial::query()->where('codigo', 'FERIA')->value('id');
    $id = ooCrear($cuenta->id, $this->gerencia, ['origen_comercial_id' => $this->otro, 'origen_detalle' => 'Feria del colegio'])->id;

    ooEditar($id, $this->gerencia);

    expect(Oportunidad::query()->findOrFail($id)->origen_detalle)->toBe('Feria del colegio')
        ->and(fn () => ooEditar($id, $this->gerencia, ['origen_detalle' => 'Otra cosa escrita']))->toThrow(DetalleDeOtroRechazado::class, 'va solo con «Otro»')
        ->and(fn () => ooEditar($id, $this->gerencia, ['origen_comercial_id' => $this->otro, 'origen_detalle' => 'Fer']))->toThrow(DetalleDeOtroRechazado::class)
        ->and(fn () => ooEditar($id, $this->gerencia, ['origen_comercial_id' => $feria, 'origen_detalle' => 'Feria del colegio']))->toThrow(DetalleDeOtroRechazado::class, 'va solo con «Otro»');

    ooEditar($id, $this->gerencia, ['origen_comercial_id' => $feria]);

    expect(Oportunidad::query()->findOrFail($id)->only(['origen_comercial_id', 'origen_detalle']))->toBe(['origen_comercial_id' => $feria, 'origen_detalle' => null]);
});
