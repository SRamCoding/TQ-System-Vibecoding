<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\TipoParametro;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\ParametroRechazado;
use App\Modules\Nucleo\Filament\Resources\Parametros\Pages\ListarParametros;
use App\Modules\Nucleo\Models\Parametro;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\TipoDocumentoIdentidad;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\PartiesService;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/*
 * El RUC del banco de detracciones decide a dónde van las detracciones (plan 1.3b, D5): se cambia con el permiso
 * de dinero (como los catálogos, plan 1.2b E6) y solo a un banco registrado, activo y con el rol Banco vigente.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

function usuarioDeDinero(string ...$permisos): User
{
    return User::factory()->create()->givePermissionTo($permisos === [] ? ['parametros.editar-sensibles'] : $permisos);
}

/**
 * @return array<string, mixed>
 */
function cambioBancoDetracciones(string $ruc): array
{
    $version = Parametro::query()->where('grupo', 'parties')->where('clave', 'ruc_banco_detracciones')->value('version');

    return ['parametro' => 'parties.ruc_banco_detracciones', 'valor' => $ruc, 'version' => $version];
}

function bancoRegistrado(string $ruc, bool $conRol = true, bool $activo = true): Party
{
    $banco = Party::factory()->juridica()->create([
        'tipo_documento_id' => TipoDocumentoIdentidad::query()->where('codigo', Catalogo::RUC)->value('id'),
        'numero_documento' => $ruc,
        'razon_social' => 'Banco de Prueba S.A.',
    ]);

    if ($conRol) {
        app(PartiesService::class)->asignarRol($banco->id, 'banco', CarbonImmutable::parse('2026-01-01'));
    }

    // Un rol se asigna a una party activa: se desactiva después.
    if (! $activo) {
        $banco->update(['es_activo' => false]);
    }

    return $banco;
}

test('es un parámetro de dinero y su permiso existe', function () {
    expect(app(ParametrosService::class)->esSensible('parties.ruc_banco_detracciones'))->toBeTrue()
        ->and(app(ParametrosService::class)->esSensible('tributario.igv_porcentaje'))->toBeTrue()
        ->and(app(ParametrosService::class)->esSensible('tipo_cambio.dias_tolerancia'))->toBeTrue()
        ->and(app(ParametrosService::class)->esSensible('general.moneda_local'))->toBeTrue()
        ->and(Permission::query()->where('name', 'parametros.editar-sensibles')->exists())->toBeTrue();
});

test('«Cambiar parámetro» no lo cambia, aunque la persona tenga permiso de editar parámetros', function () {
    bancoRegistrado('20999999990');

    expect(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.cambiar-parametro', cambioBancoDetracciones('20999999990'), usuarioDeDinero('parametros.editar')))
        ->toThrow(ParametroRechazado::class, 'es un parámetro de dinero')
        ->and(app(ParametrosService::class)->rucBancoDetracciones())->toBe('20100030595');
});

test('sin el permiso de dinero, «Cambiar parámetro de dinero» se rechaza', function () {
    expect(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.cambiar-parametro-sensible', cambioBancoDetracciones('20999999990'), usuarioDeDinero('parametros.editar')))
        ->toThrow(AccionRechazada::class, 'No tienes permiso para «Cambiar parámetro de dinero».');
});

test('con el permiso de dinero, cambia a un banco registrado con el rol vigente', function () {
    bancoRegistrado('20999999990');

    app(EjecutorAcciones::class)->ejecutar('nucleo.cambiar-parametro-sensible', cambioBancoDetracciones('209-9999999-0'), usuarioDeDinero());

    expect(app(ParametrosService::class)->rucBancoDetracciones())->toBe('20999999990');
});

test('se rechaza un RUC que no sirve como banco de detracciones', function (Closure $preparar, string $ruc, string $mensaje) {
    $preparar();

    expect(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.cambiar-parametro-sensible', cambioBancoDetracciones($ruc), usuarioDeDinero()))
        ->toThrow(ParametroRechazado::class, $mensaje)
        ->and(app(ParametrosService::class)->rucBancoDetracciones())->toBe('20100030595');
})->with([
    'dígito verificador incorrecto' => [fn () => null, '20999999991', 'su último dígito no corresponde'],
    'no registrado' => [fn () => null, '20999999990', 'no hay ninguna persona o empresa registrada con el RUC 20999999990'],
    'sin el rol Banco' => [fn () => bancoRegistrado('20999999990', conRol: false), '20999999990', 'no tiene el rol Banco vigente'],
    'desactivado' => [fn () => bancoRegistrado('20999999990', activo: false), '20999999990', 'está desactivada'],
]);

test('«Cambiar parámetro de dinero» no sirve para los demás parámetros', function () {
    Parametro::factory()->create(['grupo' => 'pruebas', 'clave' => 'plazo', 'tipo' => TipoParametro::Entero, 'valor' => '30']);
    $plazo = ['parametro' => 'pruebas.plazo', 'valor' => '10', 'version' => 1];

    expect(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.cambiar-parametro-sensible', $plazo, usuarioDeDinero()))
        ->toThrow(ParametroRechazado::class, 'no es un parámetro de dinero');
});

test('en la pantalla, el parámetro de dinero solo ofrece su propia acción', function () {
    Filament::setCurrentPanel('app');
    $banco = Parametro::query()->where('clave', 'ruc_banco_detracciones')->firstOrFail();
    $plazo = Parametro::factory()->create(['grupo' => 'pruebas', 'clave' => 'plazo', 'tipo' => TipoParametro::Entero, 'valor' => '30']);

    test()->actingAs(usuarioDeDinero('catalogos.ver', 'parametros.editar', 'parametros.editar-sensibles'));

    Livewire::test(ListarParametros::class)
        ->assertActionHidden(TestAction::make('nucleoCambiarParametro')->table($banco))
        ->assertActionVisible(TestAction::make('nucleoCambiarParametroSensible')->table($banco))
        ->assertActionVisible(TestAction::make('nucleoCambiarParametro')->table($plazo))
        ->assertActionHidden(TestAction::make('nucleoCambiarParametroSensible')->table($plazo));

    test()->actingAs(usuarioDeDinero('catalogos.ver', 'parametros.editar'));

    Livewire::test(ListarParametros::class)
        ->assertActionHidden(TestAction::make('nucleoCambiarParametroSensible')->table($banco));
});
