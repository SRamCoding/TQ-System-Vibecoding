<?php

declare(strict_types=1);

use App\Modules\Nucleo\ConfiguracionInicial\DefinicionPaso;
use App\Modules\Nucleo\ConfiguracionInicial\RegistroPasosConfiguracion;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\ParametroRechazado;
use App\Modules\Nucleo\Excepciones\PasoConfiguracionRechazado;
use App\Modules\Nucleo\Filament\Pages\ConfiguracionInicial;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\ListarParties;
use App\Modules\Nucleo\Filament\Resources\Parties\Pages\VerParty;
use App\Modules\Nucleo\Filament\Resources\Usuarios\Pages\VerUsuario;
use App\Modules\Nucleo\Filament\Widgets\AvisoConfiguracionInicial;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\ConfiguracionPaso;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\ConfiguracionInicialService;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\SeguridadService;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
});

it('un enlace del panel a una pantalla del sistema se abre ahí mismo, con la clase y los parámetros de su ruta', function () {
    $persona = User::factory()->create();
    test()->actingAs(User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR));

    expect(ConfiguracionInicial::pantallaDe('/seguridad/usuarios/'.$persona->id))
        ->toBe(['clase' => VerUsuario::class, 'parametros' => ['record' => (string) $persona->id]]);
});

it('lo que no es una pantalla del sistema no se abre en el panel: otro sitio, una ruta suelta, el propio asistente', function (string $ruta) {
    expect(ConfiguracionInicial::pantallaDe($ruta))->toBeNull();
})->with([
    'otro sitio' => 'https://ejemplo.test/seguridad/usuarios/1',
    'sin barra inicial' => 'seguridad/usuarios/1',
    'ruta que no existe' => '/no/existe/nada',
    'ruta que no es de Filament' => '/up',
    'el propio asistente' => '/configuracion/inicial',
    'la sección, que solo redirige' => '/configuracion',
]);

it('una pantalla que la persona no puede ver no se abre en el panel: el enlace sigue su camino', function () {
    $persona = User::factory()->create();
    $soloAsistente = User::factory()->create();
    $soloAsistente->givePermissionTo(ConfiguracionInicialService::PERMISO);
    test()->actingAs($soloAsistente);

    expect(ConfiguracionInicial::pantallaDe('/seguridad/usuarios/'.$persona->id))->toBeNull()
        ->and(ConfiguracionInicial::pantallaDe('/seguridad/usuarios'))->toBeNull()
        ->and(ConfiguracionInicial::pantallaDe('/seguridad/usuarios/999999'))->toBeNull();

    // Un paso cuya pantalla no puede ver: el panel lo dice, no la monta.
    Livewire::actingAs($soloAsistente)->test(ConfiguracionInicial::class)
        ->call('abrir', 'nucleo.usuarios')->assertSeeHtml('data-tq-sin-permiso')
        ->call('abrirEnPanel', '/seguridad/usuarios/'.$persona->id)->assertReturned(false)->assertSet('ficha', null);
});

it('abre la ficha dentro del panel solo con un paso abierto, y volver la quita', function () {
    $persona = User::factory()->create();
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);

    Livewire::actingAs($administrador)->test(ConfiguracionInicial::class)
        ->call('abrirEnPanel', '/seguridad/usuarios/'.$persona->id)->assertReturned(false)->assertSet('ficha', null)
        ->call('abrir', 'nucleo.usuarios')
        ->call('abrirEnPanel', '/seguridad/usuarios/'.$persona->id)->assertReturned(true)->assertSet('ficha', '/seguridad/usuarios/'.$persona->id)
        ->call('abrirEnPanel', 'https://ejemplo.test/x')->assertReturned(false)
        ->call('volver')->assertSet('ficha', null)
        ->call('abrirEnPanel', '/seguridad/usuarios/'.$persona->id)
        ->call('abrir', 'nucleo.parametros')->assertSet('ficha', null);
});

it('el núcleo trae sus 10 pasos en orden; la IA es opcional; cada resumen se calcula sin fallar', function () {
    $pasos = app(RegistroPasosConfiguracion::class)->pasos();

    expect(array_keys($pasos))->toBe(['nucleo.empresa', 'nucleo.fundacion', 'nucleo.organigrama', 'nucleo.usuarios', 'nucleo.roles', 'nucleo.catalogos', 'nucleo.parametros', 'nucleo.aprobaciones', 'nucleo.alertas', 'nucleo.ia'])
        ->and(array_keys(array_filter($pasos, fn (DefinicionPaso $paso): bool => ! $paso->obligatorio)))->toBe(['nucleo.ia'])
        ->and(array_map(fn (DefinicionPaso $paso): ?string => $paso->resumen === null ? null : ($paso->resumen)(), $pasos)['nucleo.fundacion'])->toBe('Sin fecha')
        ->and(fn () => app(RegistroPasosConfiguracion::class)->registrar(new DefinicionPaso('nucleo.ia', 'Otra', 'Otra', 'heroicon-o-beaker', ConfiguracionInicial::class)))->toThrow(InvalidArgumentException::class, 'ya está registrado');
});

it('marcar y reabrir pasan por las acciones registradas, con su permiso, y quedan en el historial', function () {
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
    $acciones = app(EjecutorAcciones::class);
    $servicio = app(ConfiguracionInicialService::class);

    $acciones->ejecutar('nucleo.configuracion-inicial.marcar-paso-listo', ['paso' => 'nucleo.organigrama'], $administrador);
    $acciones->ejecutar('nucleo.configuracion-inicial.marcar-paso-listo', ['paso' => 'nucleo.organigrama'], $administrador);
    expect($servicio->listos())->toBe(['nucleo.organigrama'])
        ->and(ConfiguracionPaso::query()->count())->toBe(1);

    $acciones->ejecutar('nucleo.configuracion-inicial.reabrir-paso', ['paso' => 'nucleo.organigrama'], $administrador);
    $fila = ConfiguracionPaso::query()->sole();

    expect($servicio->listos())->toBe([])
        ->and(AuditoriaCambio::query()->where('auditable_type', 'configuracion_paso')->where('auditable_id', $fila->id)->count())->toBeGreaterThanOrEqual(2)
        ->and($fila->updated_by)->toBe($administrador->id);
});

it('sin el permiso no se marca; un paso que no existe se rechaza; la base solo acepta claves de paso', function () {
    $sinPermiso = User::factory()->create();
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);

    expect(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.configuracion-inicial.marcar-paso-listo', ['paso' => 'nucleo.organigrama'], $sinPermiso))->toThrow(AccionRechazada::class)
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.configuracion-inicial.marcar-paso-listo', ['paso' => 'nucleo.no-existe'], $administrador))->toThrow(PasoConfiguracionRechazado::class, 'No existe')
        ->and(fn () => DB::transaction(fn () => DB::table('configuracion_pasos')->insert(['paso' => 'Con Espacios', 'es_listo' => true, 'created_by' => $administrador->id])))->toThrow(QueryException::class, 'configuracion_pasos_paso_check')
        ->and(ConfiguracionPaso::query()->count())->toBe(0);
});

it('faltan son los obligatorios sin marcar, en orden; el opcional nunca falta', function () {
    $servicio = app(ConfiguracionInicialService::class);
    foreach (['nucleo.empresa', 'nucleo.fundacion', 'nucleo.organigrama', 'nucleo.usuarios', 'nucleo.roles', 'nucleo.catalogos', 'nucleo.parametros', 'nucleo.aprobaciones'] as $paso) {
        test()->actingAs(User::factory()->create());
        $servicio->marcarListo($paso);
    }

    expect(array_map(fn (DefinicionPaso $paso): string => $paso->clave, $servicio->faltan()))->toBe(['nucleo.alertas']);

    $servicio->marcarListo('nucleo.alertas');
    expect($servicio->faltan())->toBe([]);
});

it('la ficha de Techquk es una empresa activa con ese RUC; sin elegir, el paso abre la lista y, elegida, su ficha', function () {
    $techquk = Party::factory()->juridica()->create(['razon_social' => 'Empresa de Prueba S.A.C.']);
    $persona = Party::factory()->create();
    $inactiva = Party::factory()->juridica()->inactiva()->create();
    $parametros = app(ParametrosService::class);
    $paso = app(RegistroPasosConfiguracion::class)->paso('nucleo.empresa');

    expect(app(EmpresaService::class)->ficha())->toBeNull()
        ->and($paso->abrir()['clase'])->toBe(ListarParties::class)
        ->and(($paso->resumen)())->toBe('Sin elegir');

    foreach (['20000000001', $persona->numero_documento, $inactiva->numero_documento, 'cualquier cosa'] as $malo) {
        expect(fn () => $parametros->cambiarDesdeTexto(EmpresaService::PARAMETRO, $malo, 1))->toThrow(ParametroRechazado::class, 'empresa activa');
    }

    // Con guiones o espacios se toma igual: se guardan solo los dígitos.
    $parametros->cambiarDesdeTexto(EmpresaService::PARAMETRO, ' '.substr($techquk->numero_documento, 0, 2).'-'.substr($techquk->numero_documento, 2), 1);

    expect(app(EmpresaService::class)->ficha()?->id)->toBe($techquk->id)
        ->and($paso->abrir())->toBe(['clase' => VerParty::class, 'parametros' => ['record' => (string) $techquk->id]])
        ->and(($paso->resumen)())->toBe('Empresa de Prueba S.A.C. · RUC '.$techquk->numero_documento);

    // «sin ficha» vuelve a la lista.
    $parametros->cambiarDesdeTexto(EmpresaService::PARAMETRO, 'Sin ficha', 2);
    expect(app(EmpresaService::class)->ficha())->toBeNull();
});

it('solo quien tiene el permiso entra; la lista trae cada paso con su resumen y el avance', function () {
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);

    test()->actingAs(User::factory()->create())->get(ConfiguracionInicial::getUrl())->assertForbidden();

    $html = Livewire::actingAs($administrador)->test(ConfiguracionInicial::class)->html();

    foreach (array_keys(app(RegistroPasosConfiguracion::class)->pasos()) as $clave) {
        expect($html)->toContain('data-paso="'.$clave.'"');
    }

    expect($html)->toContain('data-tq-avance="0/9"')->toContain('Sin fecha')->toContain('Sin elegir');
});

it('«Marcar como listo» y «Reabrir» de la lista pasan por sus acciones', function () {
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);

    Livewire::actingAs($administrador)->test(ConfiguracionInicial::class)
        ->callAction('marcarListo', arguments: ['paso' => 'nucleo.organigrama'])
        ->assertSeeHtml('data-tq-avance="1/9"');
    expect(app(ConfiguracionInicialService::class)->listos())->toBe(['nucleo.organigrama']);

    Livewire::actingAs($administrador)->test(ConfiguracionInicial::class)->callAction('reabrir', arguments: ['paso' => 'nucleo.organigrama']);
    expect(app(ConfiguracionInicialService::class)->listos())->toBe([]);
});

it('«Elegir la ficha de Techquk» guarda su RUC con la acción de los parámetros, y el paso abre esa ficha', function () {
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
    $techquk = Party::factory()->juridica()->create();

    Livewire::actingAs($administrador)->test(ConfiguracionInicial::class)
        ->callAction('elegirFicha', ['valor' => 'RUC '.$techquk->numero_documento])
        ->assertHasNoActionErrors();

    expect(app(EmpresaService::class)->ficha()?->id)->toBe($techquk->id)
        ->and(app(RegistroPasosConfiguracion::class)->paso('nucleo.empresa')->abrir()['clase'])->toBe(VerParty::class);
});

it('con la ficha elegida, el paso de la empresa muestra el encabezado de los documentos y lo que falta (plan 1.15, D4)', function () {
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
    $pantalla = Livewire::actingAs($administrador)->test(ConfiguracionInicial::class)->call('abrir', 'nucleo.empresa');
    $pantalla->assertDontSeeHtml('data-tq-vista-previa-empresa');

    $techquk = Party::factory()->juridica()->create(['razon_social' => 'Empresa de Prueba S.A.C.', 'telefono' => '074 000000']);
    app(ParametrosService::class)->cambiarDesdeTexto(EmpresaService::PARAMETRO, $techquk->numero_documento, 1);

    $pantalla->call('alCambiarLaFicha')
        ->assertSeeHtml('data-tq-vista-previa-empresa')
        ->assertSee(['Así salen sus datos en los documentos', 'Empresa de Prueba S.A.C.', 'RUC '.$techquk->numero_documento, '074 000000'])
        ->assertSee(['Sin logo', 'Sin correo'])
        ->assertDontSee('Sin teléfono');

    // Otro paso, o una pantalla abierta dentro del panel: sin vista previa.
    $pantalla->call('abrir', 'nucleo.parametros')->assertDontSeeHtml('data-tq-vista-previa-empresa');
});

it('la vista previa se vuelve a dibujar cuando la ficha o sus archivos cambian dentro del panel', function () {
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
    $techquk = Party::factory()->juridica()->create();
    app(ParametrosService::class)->cambiarDesdeTexto(EmpresaService::PARAMETRO, $techquk->numero_documento, 1);
    $pantalla = Livewire::actingAs($administrador)->test(ConfiguracionInicial::class)->call('abrir', 'nucleo.empresa')->assertSee('Sin correo');

    $techquk->update(['email' => 'contacto@empresa-de-prueba.test']);
    $pantalla->dispatch('ficha-party-cambiada')->assertDontSee('Sin correo')->assertSee('contacto@empresa-de-prueba.test');

    $techquk->update(['telefono' => null, 'email' => null]);
    $pantalla->dispatch('archivos-cambiados')->assertSee('Sin correo');
});

it('el inicio avisa lo que falta solo a quien puede configurar, y el aviso se va al completar los obligatorios', function () {
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);

    test()->actingAs(User::factory()->create());
    expect(AvisoConfiguracionInicial::canView())->toBeFalse();

    test()->actingAs($administrador);
    expect(AvisoConfiguracionInicial::canView())->toBeTrue()
        ->and(Livewire::test(AvisoConfiguracionInicial::class)->html())->toContain('Faltan 9 pasos')->toContain('Fecha de fundación')->not->toContain('Inteligencia artificial');

    foreach (app(ConfiguracionInicialService::class)->faltan() as $paso) {
        app(ConfiguracionInicialService::class)->marcarListo($paso->clave);
    }

    expect(AvisoConfiguracionInicial::canView())->toBeFalse();
});

it('reabrir un paso que nunca se marcó no deja fila ni historial', function () {
    test()->actingAs(User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR));

    app(ConfiguracionInicialService::class)->reabrir('nucleo.alertas');

    expect(ConfiguracionPaso::query()->count())->toBe(0)
        ->and(AuditoriaCambio::query()->where('auditable_type', 'configuracion_paso')->count())->toBe(0);
});

it('elegir la ficha: si otro cambió el parámetro con la ventana abierta, se rechaza; un RUC sin ficha también; sin permiso de parámetros, no aparece', function () {
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
    [$una, $otra] = Party::factory()->juridica()->count(2)->create()->all();

    Livewire::actingAs($administrador)->test(ConfiguracionInicial::class)
        ->mountAction('elegirFicha')
        ->tap(fn () => app(ParametrosService::class)->cambiarDesdeTexto(EmpresaService::PARAMETRO, $otra->numero_documento, 1))
        ->setActionData(['valor' => 'RUC '.$una->numero_documento])
        ->callMountedAction()
        ->assertNotified();
    expect(app(EmpresaService::class)->ficha()?->id)->toBe($otra->id);

    Livewire::actingAs($administrador)->test(ConfiguracionInicial::class)
        ->callAction('elegirFicha', ['valor' => 'RUC 20000000001'])
        ->assertHasActionErrors(['valor']);
    expect(app(EmpresaService::class)->ficha()?->id)->toBe($otra->id);

    $soloAsistente = User::factory()->create();
    $soloAsistente->givePermissionTo(ConfiguracionInicialService::PERMISO);
    Livewire::actingAs($soloAsistente)->test(ConfiguracionInicial::class)->assertActionHidden('elegirFicha');
});
