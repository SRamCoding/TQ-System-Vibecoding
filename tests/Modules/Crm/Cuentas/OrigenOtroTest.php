<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\OtrosDeOrigenDeCuentas;
use App\Modules\Nucleo\database\seeders\CatalogosSeeder;
use App\Modules\Nucleo\database\seeders\EstadosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Excepciones\DetalleDeOtroRechazado;
use App\Modules\Nucleo\Models\OrigenComercial;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\CatalogosService;

/*
 * El origen «Otro» de una cuenta (plan 2.0, paso 6, D5): con él, la cuenta guarda cuál es; sin él, nada; y lo escrito
 * llega a «Lo más escrito en Otro». Por la acción registrada, sin pantalla. Datos inventados.
 */

beforeEach(function () {
    $this->seed([ParametrosSeeder::class, PermisosSeeder::class, EstadosSeeder::class, CatalogosSeeder::class]);
    $this->editor = User::factory()->create()->givePermissionTo('crm.cuentas.editar');
    $this->cuenta = Cuenta::factory()->create();
    $this->otro = (int) OrigenComercial::query()->where('codigo', 'OTRO')->value('id');
    $this->feria = (int) OrigenComercial::query()->where('codigo', 'FERIA')->value('id');
});

function editarOrigen(Cuenta $cuenta, User $quien, int $origen, ?string $detalle = null): mixed
{
    return app(EjecutorAcciones::class)->ejecutar('crm.cuentas.editar', array_filter(['cuenta_id' => $cuenta->id, 'version' => $cuenta->refresh()->version, 'origen_comercial_id' => $origen, 'origen_detalle' => $detalle], fn (mixed $v): bool => $v !== null), $quien);
}

test('con «Otro» se escribe cuál es; sin detalle o con uno corto, se rechaza', function () {
    expect(fn () => editarOrigen($this->cuenta, $this->editor, $this->otro))->toThrow(DetalleDeOtroRechazado::class, 'Elegiste «Otro» en el origen')
        ->and(fn () => editarOrigen($this->cuenta, $this->editor, $this->otro, 'Radio'))->toThrow(DetalleDeOtroRechazado::class);

    $resumen = editarOrigen($this->cuenta, $this->editor, $this->otro, 'Aviso en la radio local');

    expect($this->cuenta->refresh()->origen_detalle)->toBe('Aviso en la radio local')
        ->and($resumen->origenDetalle)->toBe('Aviso en la radio local');
});

test('al cambiar a un origen de la lista, el detalle se borra; con detalle sin «Otro», se rechaza', function () {
    editarOrigen($this->cuenta, $this->editor, $this->otro, 'Aviso en la radio local');
    editarOrigen($this->cuenta, $this->editor, $this->feria);

    expect($this->cuenta->refresh()->origen_detalle)->toBeNull()
        ->and(fn () => editarOrigen($this->cuenta, $this->editor, $this->feria, 'Aviso en la radio local'))->toThrow(DetalleDeOtroRechazado::class, 'va solo con «Otro»');
});

test('lo escrito en el origen «Otro» de las cuentas llega a «Lo más escrito en Otro»', function () {
    editarOrigen($this->cuenta, $this->editor, $this->otro, 'Aviso en la radio local');
    editarOrigen(Cuenta::factory()->create(), $this->editor, $this->otro, 'aviso en la RADIO local');

    $detalles = app(CatalogosService::class)->detallesDeOtro(Catalogo::OrigenesComerciales);

    expect(array_map(fn ($d): array => [$d->texto, $d->veces], $detalles))->toBe([['aviso en la RADIO local', 2]])
        ->and((new OtrosDeOrigenDeCuentas)->detallesDeOtro(Catalogo::RedesSociales, null))->toBe([]);
});
