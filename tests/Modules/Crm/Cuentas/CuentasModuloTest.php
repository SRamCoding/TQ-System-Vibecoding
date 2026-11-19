<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Parametros\ValidadorDiasSinActividad;
use App\Modules\Nucleo\database\seeders\EstadosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Seguridad\PermisosLegibles;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use App\Modules\Nucleo\Services\NavegacionService;
use App\Modules\Nucleo\Services\ParametrosService;

/*
 * Esqueleto de Crm/Cuentas (plan 2.20, paso 2): lo que el módulo declara al núcleo al arrancar.
 */

test('declara sus permisos de módulo, que Roles y permisos agrupa en «CRM · Cuentas»', function () {
    $this->seed(PermisosSeeder::class);
    $registro = app(RegistroPermisos::class);

    expect($registro->declarados())->toContain('crm.cuentas.ver', 'crm.cuentas.atender')
        ->and($registro->nombre('crm.cuentas.ver'))->toBe('Ver cuentas')
        ->and(PermisosLegibles::grupo('crm.cuentas.atender'))->toBe('CRM · Cuentas');
});

test('el parámetro de días sin actividad nace en 90 y acepta de 7 a 365', function () {
    $this->seed(ParametrosSeeder::class);
    $validador = new ValidadorDiasSinActividad;

    expect(app(ParametrosService::class)->entero(CuentasServiceProvider::PARAMETRO_DIAS_SIN_ACTIVIDAD))->toBe(90)
        ->and($validador->rechazo(7))->toBeNull()
        ->and($validador->rechazo(365))->toBeNull()
        ->and($validador->rechazo(6))->not->toBeNull()
        ->and($validador->rechazo(366))->not->toBeNull();
});

test('la lista de motivos para desactivar nace con sus tres motivos de partida y su «Otro»', function () {
    $this->seed(EstadosSeeder::class);

    $familia = TipoMotivo::query()->where('codigo', CuentasServiceProvider::FAMILIA_DESACTIVAR)->firstOrFail();
    expect(Motivo::query()->where('tipo_motivo_id', $familia->id)->orderBy('id')->pluck('nombre')->all())
        ->toBe(['Otro', 'Dejó de operar', 'Creada por error (RUC equivocado)', 'Ya no es mercado para Techquk']);
});

test('la caja «Cuentas» va en el área CRM y solo la ve quien tiene crm.cuentas.ver', function () {
    $this->seed(PermisosSeeder::class);
    $cajas = array_filter(app(RegistroModulos::class)->todas(), fn (CajaModulo $caja): bool => $caja->clave === 'cuentas');
    $caja = array_values($cajas)[0];
    $conPermiso = User::factory()->create();
    $conPermiso->givePermissionTo('crm.cuentas.ver');
    $claves = fn (User $usuario): array => array_map(fn (CajaModulo $c): string => $c->clave, app(NavegacionService::class)->permitidos($usuario));

    expect($caja->area)->toBe(AreaModulo::Crm)
        ->and($caja->url)->toBe('/cuentas')
        ->and($caja->icono)->toBe('heroicon-o-building-storefront')
        ->and($caja->preguntas)->toHaveCount(4)
        ->and($claves($conPermiso))->toContain('cuentas')
        ->and($claves(User::factory()->create()))->not->toContain('cuentas');
});
