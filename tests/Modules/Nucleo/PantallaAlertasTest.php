<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\Alertas\ConfigurarAlerta;
use App\Modules\Nucleo\Alertas\RegistroAlertas;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\AlertaRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Filament\Pages\Alertas;
use App\Modules\Nucleo\Models\AlertaTipo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Seguridad\CrucesSinControl;
use App\Modules\Nucleo\Services\AlertasService;
use App\Modules\Nucleo\Services\SeguridadService;
use BladeUI\Icons\Factory as IconFactory;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/*
 * Configuración → Alertas (plan 1.10, D2, D5 y D7): una caja por alerta; su panel elige roles (al menos uno), si está
 * activa (una obligatoria no se apaga) y sus textos, que conservan siempre sus datos (D5, opción B). Personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

function quienConfiguraAlertas(): User
{
    return User::factory()->create()->givePermissionTo(ConfigurarAlerta::PERMISO);
}

function tipoDe(string $clave): AlertaTipo
{
    return AlertaTipo::query()->where('codigo', $clave)->sole();
}

/** @param array<string, mixed> $cambios */
function configurarCruces(array $cambios = []): void
{
    $tipo = tipoDe(CrucesSinControl::ALERTA);
    $valores = [
        'roles' => [Role::findByName(SeguridadService::ROL_GERENCIA_GENERAL)->id],
        'activa' => true,
        'anticipacion' => null,
        'asunto' => 'Atención: {cantidad} cruces',
        'texto' => 'Regístralos hoy.',
        'version' => $tipo->version,
        ...$cambios,
    ];

    app(AlertasService::class)->configurar(CrucesSinControl::ALERTA, $valores['roles'], $valores['activa'], $valores['anticipacion'], $valores['asunto'], $valores['texto'], $valores['version']);
}

test('cada alerta del núcleo tiene un ícono que existe, distinto de las demás, y su descripción', function () {
    $alertas = app(RegistroAlertas::class)->alertas();

    expect(array_unique(array_map(fn ($a): string => $a->icono, $alertas)))->toHaveCount(count($alertas));

    foreach ($alertas as $alerta) {
        expect($alerta->descripcion)->not->toBe('')
            ->and(fn () => app(IconFactory::class)->svg($alerta->icono))->not->toThrow(Throwable::class);
    }
});

test('la acción se registra con su permiso, que el administrador trae de fábrica', function () {
    expect(app(RegistroAcciones::class)->obtener('nucleo.alertas.configurar')->permiso())->toBe('alertas.configurar')
        ->and(Role::findByName(SeguridadService::ROL_ADMINISTRADOR)->hasPermissionTo('alertas.configurar'))->toBeTrue();
});

test('sin permiso la pantalla no se abre; con él, muestra las cajas por grupo', function () {
    test()->actingAs(User::factory()->create())->get(Alertas::getUrl())->assertForbidden();

    test()->actingAs(quienConfiguraAlertas())->get(Alertas::getUrl())->assertOk()
        ->assertSeeInOrder(['Control interno', 'Cruces de funciones sin control', 'Autoaprobación por revisar', 'Fallas del sistema', 'Proceso externo que falló'])
        ->assertSee('Buscar alerta');
});

test('una alerta cuyos roles no tienen a nadie activo va marcada; con alguien, no', function () {
    test()->actingAs(quienConfiguraAlertas())->get(Alertas::getUrl())->assertSee('Sin nadie');

    foreach ([SeguridadService::ROL_GERENCIA_GENERAL, SeguridadService::ROL_ADMINISTRADOR] as $rol) {
        User::factory()->create()->assignRole($rol);
    }

    test()->actingAs(quienConfiguraAlertas())->get(Alertas::getUrl())->assertDontSee('Sin nadie');
});

test('abrir una alerta no recarga: el panel trae su configuración de ahora y la URL lo sigue; cerrar lo quita', function () {
    $pagina = Livewire::withQueryParams([])->actingAs(quienConfiguraAlertas())->test(Alertas::class)
        ->call('abrir', CrucesSinControl::ALERTA)
        ->assertSet('alerta', CrucesSinControl::ALERTA)
        ->assertSet('data.asunto', 'Hay {cantidad} cruces de funciones sin control')
        ->assertSet('data.version', 1)
        ->assertSet('data.roles', [Role::findByName(SeguridadService::ROL_GERENCIA_GENERAL)->id])
        ->assertSee('+ Cantidad de cruces')
        ->assertSee('Volver al texto por defecto');

    expect(array_values($pagina->instance()->getBreadcrumbs()))->toBe(['Configuración', 'Alertas', 'Cruces de funciones sin control']);

    $pagina->call('cerrar')->assertSet('alerta', null);
    $pagina->call('abrir', 'inventada')->assertSet('alerta', null);
});

test('guardar desde el panel cambia roles y textos; «por defecto» vuelve a los de fábrica', function () {
    $compras = Role::findOrCreate('compras-prueba', 'web');

    Livewire::withQueryParams(['alerta' => CrucesSinControl::ALERTA])->actingAs(quienConfiguraAlertas())->test(Alertas::class)
        ->set('data.roles', [$compras->id])
        ->set('data.texto', 'Hoy: {cantidad} sin control.')
        ->call('guardar')
        ->assertNotified('Guardado.')
        ->assertSet('data.version', 2)
        ->call('textoPorDefecto')
        ->assertSet('data.texto', 'Hay personas que juntan permisos que no deberían ir juntos, sin un control registrado. Registra su control en Usuarios y organigrama, pestaña Conflictos.')
        ->call('guardar');

    $tipo = tipoDe(CrucesSinControl::ALERTA);

    expect($tipo->roles()->pluck('rol_id')->all())->toBe([$compras->id])
        ->and($tipo->texto)->toBeNull()
        ->and($tipo->version)->toBe(3);
});

test('desde el panel, un texto sin su dato no se guarda y lo dice', function () {
    Livewire::withQueryParams(['alerta' => CrucesSinControl::ALERTA])->actingAs(quienConfiguraAlertas())->test(Alertas::class)
        ->set('data.asunto', 'Hay cruces')
        ->call('guardar')
        ->assertNotified('Falta el dato «Cantidad de cruces»: insértalo con su botón, debajo del texto.');

    expect(tipoDe(CrucesSinControl::ALERTA)->asunto)->toBeNull();
});

test('las reglas al configurar: al menos un rol, una obligatoria no se apaga, los datos completos y ninguno inventado', function (array $cambios, string $mensaje) {
    expect(fn () => configurarCruces($cambios))->toThrow(AlertaRechazada::class, $mensaje);
})->with([
    'sin roles' => [['roles' => []], 'al menos a un rol'],
    'un rol que no existe' => [['roles' => [999999]], 'ya no existe'],
    'apagar una obligatoria' => [['activa' => false], 'es obligatoria'],
    'falta el dato' => [['asunto' => 'Hay cruces'], 'Falta el dato «Cantidad de cruces»'],
    'dato inventado' => [['texto' => 'Faltan {cantida}.'], '«{cantida}» no es un dato de esta alerta. Puedes usar: {cantidad}.'],
    'anticipación donde no hay' => [['anticipacion' => '30'], 'no avisa con anticipación'],
]);

test('una alerta que no es obligatoria se apaga; un texto igual al de fábrica se guarda vacío', function () {
    $tipo = tipoDe('nucleo.autoaprobacion-por-revisar');

    app(AlertasService::class)->configurar('nucleo.autoaprobacion-por-revisar', [Role::findByName(SeguridadService::ROL_ADMINISTRADOR)->id], false, null, '{persona} se autoaprobó {documento}', 'Revisa esta firma.', $tipo->version);

    $tipo->refresh();

    expect($tipo->es_activa)->toBeFalse()
        ->and($tipo->asunto)->toBeNull()
        ->and($tipo->texto)->toBe('Revisa esta firma.');
});

test('con la versión vieja no se guarda: otra persona la cambió', function () {
    configurarCruces();

    expect(fn () => configurarCruces(['version' => 1]))->toThrow(VersionDesactualizada::class);
});

test('la acción corre sin la interfaz, con su permiso', function () {
    $datos = [
        'alerta' => CrucesSinControl::ALERTA,
        'roles' => [Role::findByName(SeguridadService::ROL_ADMINISTRADOR)->id],
        'es_activa' => true,
        'anticipacion_dias' => null,
        'asunto' => 'Cruces: {cantidad}',
        'texto' => 'Revisa.',
        'version' => 1,
    ];

    expect(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.alertas.configurar', $datos, User::factory()->create()))->toThrow(AccionRechazada::class);

    app(EjecutorAcciones::class)->ejecutar('nucleo.alertas.configurar', $datos, quienConfiguraAlertas());

    expect(tipoDe(CrucesSinControl::ALERTA)->asunto)->toBe('Cruces: {cantidad}');
});

test('cuáles no le llegan a nadie se calcula de una vez, igual que alerta por alerta (plan 1.12, prueba de carga)', function () {
    $servicio = app(AlertasService::class);
    $alertas = app(RegistroAlertas::class)->alertas();
    $unaPorUna = collect($alertas)->filter(fn ($alerta): bool => $servicio->sinDestinatariosPropios($servicio->tipo($alerta)))->keys()->values()->all();

    DB::enableQueryLog();
    $deUnaVez = $servicio->sinDestinatariosPropiosDe($alertas);

    expect($deUnaVez)->toBe($unaPorUna)
        ->and(count(DB::getQueryLog()))->toBeLessThanOrEqual(3);
});
