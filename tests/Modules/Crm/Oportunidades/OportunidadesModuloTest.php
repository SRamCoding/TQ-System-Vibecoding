<?php

declare(strict_types=1);

use App\Modules\Crm\Oportunidades\Enums\EtapaAbierta;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Parametros\ValidadorDiasMaximos;
use App\Modules\Crm\Oportunidades\Parametros\ValidadorPeriodoCierre;
use App\Modules\Crm\Oportunidades\Parametros\ValidadorProbabilidadCalificada;
use App\Modules\Crm\Oportunidades\Parametros\ValidadorProbabilidadNegociacion;
use App\Modules\Crm\Oportunidades\Parametros\ValidadorProbabilidadProspecto;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Seguridad\PermisosLegibles;
use App\Modules\Nucleo\Seguridad\RegistroPermisos;
use App\Modules\Nucleo\Services\NavegacionService;
use App\Modules\Nucleo\Services\ParametrosService;

/*
 * Esqueleto de Crm/Oportunidades (plan 2.21, paso 6): lo que el módulo declara al núcleo al arrancar.
 */

test('declara el permiso de ver, que Roles y permisos agrupa en «CRM · Oportunidades»', function () {
    $this->seed(PermisosSeeder::class);

    expect(app(RegistroPermisos::class)->declarados())->toContain(OportunidadesServiceProvider::PERMISO_VER)
        ->and(app(RegistroPermisos::class)->nombre('crm.oportunidades.ver'))->toBe('Ver oportunidades')
        ->and(PermisosLegibles::grupo('crm.oportunidades.ver'))->toBe('CRM · Oportunidades');
});

test('la probabilidad y los días máximos nacen con los valores del diseño: 14 días en Calificada (D2) y 15 en Propuesta enviada (plan 2.22, P2)', function () {
    $this->seed(ParametrosSeeder::class);
    $parametros = app(ParametrosService::class);

    expect(array_map(fn (EtapaAbierta $e): int => $parametros->entero($e->parametroProbabilidad()), EtapaAbierta::cases()))->toBe([10, 25, 50, 75])
        ->and(array_map(fn (EtapaAbierta $e): int => $parametros->entero($e->parametroDiasMaximos()), EtapaAbierta::cases()))->toBe([0, 14, 15, 0])
        ->and($parametros->texto(OportunidadesServiceProvider::PARAMETRO_PERIODO_CIERRE))->toBe('mes');
});

test('cada probabilidad va de 1 a 99 y no baja de una etapa abierta a la siguiente (D2)', function () {
    $this->seed(ParametrosSeeder::class);

    expect((new ValidadorProbabilidadProspecto)->rechazo(1))->toBeNull()
        ->and((new ValidadorProbabilidadProspecto)->rechazo(0))->toContain('entre 1 y 99')
        ->and((new ValidadorProbabilidadNegociacion)->rechazo(100))->toContain('entre 1 y 99')
        ->and((new ValidadorProbabilidadNegociacion)->rechazo(99))->toBeNull()
        ->and((new ValidadorProbabilidadCalificada)->rechazo(10))->toBeNull()
        ->and((new ValidadorProbabilidadCalificada)->rechazo(9))->toContain('menor que la de Prospecto (10 %)')
        ->and((new ValidadorProbabilidadCalificada)->rechazo(51))->toContain('mayor que la de Propuesta enviada (50 %)');
});

test('los días máximos aceptan 0 (sin máximo) a 365, y el período de «Cierre» mes, trimestre o año', function () {
    expect((new ValidadorDiasMaximos)->rechazo(0))->toBeNull()
        ->and((new ValidadorDiasMaximos)->rechazo(365))->toBeNull()
        ->and((new ValidadorDiasMaximos)->rechazo(-1))->not->toBeNull()
        ->and((new ValidadorDiasMaximos)->rechazo(366))->not->toBeNull()
        ->and((new ValidadorPeriodoCierre)->rechazo((new ValidadorPeriodoCierre)->normalizar(' Trimestre ')))->toBeNull()
        ->and((new ValidadorPeriodoCierre)->rechazo('semana'))->not->toBeNull();
});

test('las etapas abiertas saben su vecina', function () {
    expect(EtapaAbierta::Prospecto->anterior())->toBeNull()
        ->and(EtapaAbierta::Calificada->anterior())->toBe(EtapaAbierta::Prospecto)
        ->and(EtapaAbierta::PropuestaEnviada->siguiente())->toBe(EtapaAbierta::Negociacion)
        ->and(EtapaAbierta::Negociacion->siguiente())->toBeNull();
});

test('la caja «Oportunidades» va en el área CRM y solo la ve quien tiene crm.oportunidades.ver', function () {
    $this->seed(PermisosSeeder::class);
    $caja = array_values(array_filter(app(RegistroModulos::class)->todas(), fn (CajaModulo $c): bool => $c->clave === 'oportunidades'))[0];
    $conPermiso = User::factory()->create()->givePermissionTo(OportunidadesServiceProvider::PERMISO_VER);
    $claves = fn (User $usuario): array => array_map(fn (CajaModulo $c): string => $c->clave, app(NavegacionService::class)->permitidos($usuario));

    expect($caja->area)->toBe(AreaModulo::Crm)
        ->and($caja->url)->toBe('/oportunidades')
        ->and($caja->icono)->toBe('heroicon-o-presentation-chart-line')
        ->and($claves($conPermiso))->toContain('oportunidades')
        ->and($claves(User::factory()->create()))->not->toContain('oportunidades');
});
