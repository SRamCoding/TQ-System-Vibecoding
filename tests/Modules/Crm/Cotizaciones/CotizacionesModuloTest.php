<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Parametros\ValidadorDiasCotizacion;
use App\Modules\Crm\Cotizaciones\Parametros\ValidadorPorcentajeMargen;
use App\Modules\Nucleo\database\seeders\EstadosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Seguridad\PermisosLegibles;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use App\Modules\Nucleo\Services\ParametrosService;

/*
 * Esqueleto de Crm/Cotizaciones (plan 2.22, paso 3): lo que el módulo declara al núcleo al arrancar.
 */

test('declara el permiso de ver, que Roles y permisos agrupa en «CRM · Cotizaciones»', function () {
    $this->seed(PermisosSeeder::class);

    expect(app(RegistroPermisos::class)->declarados())->toContain(CotizacionesServiceProvider::PERMISO_VER)
        ->and(app(RegistroPermisos::class)->nombre('crm.cotizaciones.ver'))->toBe('Ver cotizaciones')
        ->and(PermisosLegibles::grupo('crm.cotizaciones.ver'))->toBe('CRM · Cotizaciones');
});

test('los parámetros nacen con los valores del diseño; los márgenes son sensibles (D10, D11, D14)', function () {
    $this->seed(ParametrosSeeder::class);
    $parametros = app(ParametrosService::class);

    expect($parametros->entero(CotizacionesServiceProvider::PARAMETRO_DIAS_VALIDEZ))->toBe(15)
        ->and($parametros->entero(CotizacionesServiceProvider::PARAMETRO_DIAS_POR_VENCER))->toBe(3)
        ->and($parametros->decimal(CotizacionesServiceProvider::PARAMETRO_MARGEN_OBJETIVO))->toBe('25.00')
        ->and($parametros->decimal(CotizacionesServiceProvider::PARAMETRO_MARGEN_MINIMO))->toBe('22.00')
        ->and($parametros->booleano(CotizacionesServiceProvider::PARAMETRO_EXIGIR_EMITIDA))->toBeTrue()
        ->and($parametros->booleano(CotizacionesServiceProvider::PARAMETRO_EXIGIR_ACEPTADA))->toBeTrue()
        ->and($parametros->esSensible(CotizacionesServiceProvider::PARAMETRO_MARGEN_OBJETIVO))->toBeTrue()
        ->and($parametros->esSensible(CotizacionesServiceProvider::PARAMETRO_MARGEN_MINIMO))->toBeTrue()
        ->and($parametros->esSensible(CotizacionesServiceProvider::PARAMETRO_DIAS_VALIDEZ))->toBeFalse();
});

test('los márgenes van de 0 a 100 y los días de 1 a 365', function () {
    expect((new ValidadorPorcentajeMargen)->rechazo('22.5'))->toBeNull()
        ->and((new ValidadorPorcentajeMargen)->rechazo('100.01'))->toBe('debe estar entre 0 y 100 %.')
        ->and((new ValidadorPorcentajeMargen)->rechazo('-1'))->not->toBeNull()
        ->and((new ValidadorDiasCotizacion)->rechazo(1))->toBeNull()
        ->and((new ValidadorDiasCotizacion)->rechazo(0))->toBe('debe estar entre 1 y 365 días.');
});

test('la lista de motivos de versión nueva nace con los suyos', function () {
    $this->seed(EstadosSeeder::class);

    expect(Motivo::query()->whereHas('tipoMotivo', fn ($q) => $q->where('codigo', CotizacionesServiceProvider::FAMILIA_NUEVA_VERSION))->where('es_otro', false)->orderBy('id')->pluck('codigo')->all())
        ->toBe(['pedido_del_cliente', 'cambio_de_alcance', 'correccion']);
});
