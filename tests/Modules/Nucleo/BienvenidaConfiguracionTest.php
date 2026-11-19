<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Filament\BienvenidaConfiguracion;
use App\Modules\Nucleo\Filament\Pages\ConfiguracionInicial;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\ConfiguracionBienvenida;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Saludo\LogoTechquk;
use App\Modules\Nucleo\Services\ConfiguracionInicialService;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    // Un día sin gran momento: la bienvenida no espera.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 09:00', Calendario::ZONA));
    Carbon::setTestNow(Carbon::parse('2026-11-10 09:00', Calendario::ZONA));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    Carbon::setTestNow();
});

it('la bienvenida sale a quien puede configurar mientras falten obligatorios; no a quien no tiene el permiso', function () {
    $servicio = app(ConfiguracionInicialService::class);
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
    $soloAsistente = User::factory()->create();
    $soloAsistente->givePermissionTo(ConfiguracionInicialService::PERMISO);
    test()->actingAs($administrador);

    expect($servicio->muestraBienvenida($administrador))->toBeTrue()
        ->and($servicio->muestraBienvenida($soloAsistente))->toBeTrue()
        ->and($servicio->muestraBienvenida(User::factory()->create()))->toBeFalse();

    // Los pasos listos son de todos: completados los obligatorios, no sale a nadie, aunque falte la IA (opcional).
    foreach ($servicio->faltan() as $paso) {
        $servicio->marcarListo($paso->clave);
    }

    expect($servicio->muestraBienvenida($administrador))->toBeFalse()
        ->and($servicio->muestraBienvenida($soloAsistente))->toBeFalse();
});

it('«Seguir después» la apaga solo para quien lo presiona, y «Volver a mostrar» la enciende; pasan por sus acciones y quedan en el historial', function () {
    $acciones = app(EjecutorAcciones::class);
    $servicio = app(ConfiguracionInicialService::class);
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
    $otro = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);

    $acciones->ejecutar('nucleo.configuracion-inicial.seguir-despues', [], $administrador);
    $acciones->ejecutar('nucleo.configuracion-inicial.seguir-despues', [], $administrador);

    expect($servicio->muestraBienvenida($administrador))->toBeFalse()
        ->and($servicio->bienvenidaApagada($administrador))->toBeTrue()
        ->and($servicio->muestraBienvenida($otro))->toBeTrue()
        ->and(ConfiguracionBienvenida::query()->count())->toBe(1);

    $acciones->ejecutar('nucleo.configuracion-inicial.mostrar-bienvenida', [], $administrador);
    $fila = ConfiguracionBienvenida::query()->sole();

    expect($servicio->muestraBienvenida($administrador))->toBeTrue()
        ->and($fila->user_id)->toBe($administrador->id)
        ->and($fila->es_apagada)->toBeFalse()
        ->and($fila->updated_by)->toBe($administrador->id)
        ->and(AuditoriaCambio::query()->where('auditable_type', 'configuracion_bienvenida')->where('auditable_id', $fila->id)->count())->toBeGreaterThanOrEqual(2);
});

it('sin el permiso no se apaga; encender una que nunca se apagó no deja fila; la base no acepta dos filas por persona', function () {
    $sinPermiso = User::factory()->create();
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);

    expect(fn () => app(EjecutorAcciones::class)->ejecutar('nucleo.configuracion-inicial.seguir-despues', [], $sinPermiso))->toThrow(AccionRechazada::class);

    app(EjecutorAcciones::class)->ejecutar('nucleo.configuracion-inicial.mostrar-bienvenida', [], $administrador);

    expect(ConfiguracionBienvenida::query()->count())->toBe(0)
        ->and(AuditoriaCambio::query()->where('auditable_type', 'configuracion_bienvenida')->count())->toBe(0);

    $fila = ['user_id' => $administrador->id, 'es_apagada' => true, 'created_by' => $administrador->id];
    DB::table('configuracion_bienvenidas')->insert($fila);

    expect(fn () => DB::transaction(fn () => DB::table('configuracion_bienvenidas')->insert($fila)))->toThrow(QueryException::class);
});

// --- La ventana (D3–D6) ---

it('sale en la primera pantalla tras iniciar sesión, encima de la que toque; en la siguiente, ya no', function () {
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);

    test()->actingAs($administrador)->withSession([LogoTechquk::MARCA_INGRESO => true])->get('/')->assertOk()
        ->assertSee('data-tq-bienvenida', false)
        ->assertSee('Te damos la bienvenida. Configuremos Techquk')
        ->assertSee('x-trap.noscroll="abierta"', false)
        ->assertSee('aria-modal="true"', false);

    test()->get('/')->assertOk()->assertDontSee('data-tq-bienvenida', false);
});

it('no sale sin el permiso, a quien la apagó, con los obligatorios listos, ni si el ingreso trae un gran momento', function () {
    $entrar = fn (User $usuario): string => (string) test()->actingAs($usuario)->withSession([LogoTechquk::MARCA_INGRESO => true])->get('/')->assertOk()->getContent();
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);

    expect($entrar(User::factory()->create()))->not->toContain('data-tq-bienvenida');

    app(ConfiguracionInicialService::class)->apagarBienvenida($administrador);
    expect($entrar($administrador))->not->toContain('data-tq-bienvenida');
    app(ConfiguracionInicialService::class)->encenderBienvenida($administrador);

    // El 28 de diciembre le toca la Navidad: la bienvenida espera al siguiente ingreso (no se apilan dos capas).
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-28 09:00', Calendario::ZONA));
    Carbon::setTestNow(Carbon::parse('2026-12-28 09:00', Calendario::ZONA));
    expect($entrar($administrador))->toContain('data-tq-gran-momento-pendiente')->not->toContain('data-tq-bienvenida');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 09:00', Calendario::ZONA));
    Carbon::setTestNow(Carbon::parse('2026-11-10 09:00', Calendario::ZONA));
    expect($entrar($administrador))->toContain('data-tq-bienvenida');

    test()->actingAs($administrador);
    foreach (app(ConfiguracionInicialService::class)->faltan() as $paso) {
        app(ConfiguracionInicialService::class)->marcarListo($paso->clave);
    }
    expect($entrar($administrador))->not->toContain('data-tq-bienvenida');
});

it('empieza por el primer obligatorio que falta, con su pantalla de siempre, el número y un punto por paso', function () {
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
    test()->actingAs($administrador);
    app(ConfiguracionInicialService::class)->marcarListo('nucleo.empresa');

    $ventana = Livewire::actingAs($administrador)->test(BienvenidaConfiguracion::class)
        ->assertSet('paso', 'nucleo.fundacion')
        ->assertSee('Paso 2 de 10 · Fecha de fundación')
        ->assertSeeHtml('data-paso-abierto="nucleo.fundacion"')
        ->assertSeeHtml(['data-tq-punto="nucleo.empresa"', 'aria-label="Paso 1: Datos de la empresa, listo"', 'aria-label="Paso 2: Fecha de fundación"'])
        ->assertSee(['Seguir después desde Configuración', 'Anterior', 'Saltar', 'Listo, siguiente']);

    expect(substr_count($ventana->html(), 'data-tq-punto='))->toBe(10)
        ->and(preg_match_all('/<button[^>]*aria-current="step"/', $ventana->html()))->toBe(1);
});

it('«Listo, siguiente» marca con su acción y avanza; «Saltar» avanza sin marcar; «Anterior» retrocede; un punto lleva a su paso', function () {
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
    $servicio = app(ConfiguracionInicialService::class);

    $ventana = Livewire::actingAs($administrador)->test(BienvenidaConfiguracion::class)->assertSet('paso', 'nucleo.empresa')
        ->callAction('listoSiguiente')->assertSet('paso', 'nucleo.fundacion');
    expect($servicio->listos())->toBe(['nucleo.empresa']);

    $ventana->call('siguiente')->assertSet('paso', 'nucleo.organigrama');
    expect($servicio->listos())->toBe(['nucleo.empresa']);

    $ventana->call('anterior')->call('anterior')->assertSet('paso', 'nucleo.empresa')
        // Ya listo: el botón dice «Siguiente» y no marca nada; desmarcar es «Reabrir».
        ->assertDontSee('Listo, siguiente')->assertSee(['Siguiente', 'Reabrir'])->assertDontSee('Saltar')
        ->call('anterior')->assertSet('paso', 'nucleo.empresa')->assertDontSee('Anterior')
        ->callAction('reabrir', arguments: ['paso' => 'nucleo.empresa'])->assertSee('Listo, siguiente');
    expect($servicio->listos())->toBe([]);

    $ventana->call('ir', 'nucleo.alertas')->assertSet('paso', 'nucleo.alertas')
        ->call('ir', 'nucleo.no-existe')->assertSet('paso', 'nucleo.alertas');
});

it('después del último, el cierre: lo hecho, lo que falta con su botón, e «Ir al inicio», que no apaga nada', function () {
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
    test()->actingAs($administrador);
    app(ConfiguracionInicialService::class)->marcarListo('nucleo.empresa');

    $ventana = Livewire::actingAs($administrador)->test(BienvenidaConfiguracion::class)
        ->call('ir', 'nucleo.ia')->call('siguiente')->assertSet('paso', null)
        ->assertSeeHtml('data-tq-bienvenida-cierre')->assertSee(['Lo que falta', 'Hecho', 'Datos de la empresa', 'Alertas', 'Ir al inicio', 'Seguir después desde Configuración'])
        ->assertDontSeeHtml('data-paso-abierto');
    expect($ventana->html())->toContain('wire:click="ir(\'nucleo.fundacion\')"');

    $ventana->call('anterior')->assertSet('paso', 'nucleo.ia')->call('siguiente')
        ->call('irAlInicio')->assertRedirect();
    expect(app(ConfiguracionInicialService::class)->bienvenidaApagada($administrador))->toBeFalse();

    // Con el último obligatorio marcado desde la ventana, el cierre dice «Todo listo».
    foreach (array_slice(app(ConfiguracionInicialService::class)->faltan(), 0, -1) as $paso) {
        app(ConfiguracionInicialService::class)->marcarListo($paso->clave);
    }
    Livewire::actingAs($administrador)->test(BienvenidaConfiguracion::class)->assertSet('paso', 'nucleo.alertas')
        ->callAction('listoSiguiente')->call('siguiente')->assertSet('paso', null)
        ->assertSee(['Todo listo', 'Ir al inicio'])->assertDontSee('Seguir después desde Configuración')->assertDontSeeHtml('data-tq-faltan');
});

it('«Seguir después» la apaga con su acción y lleva al inicio; la ✕ solo la cierra; la página la vuelve a encender', function () {
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);

    Livewire::actingAs($administrador)->test(BienvenidaConfiguracion::class)
        ->call('cerrar')->assertSet('cerrada', true)->assertDontSeeHtml('data-tq-bienvenida');
    expect(app(ConfiguracionInicialService::class)->bienvenidaApagada($administrador))->toBeFalse();

    Livewire::actingAs($administrador)->test(ConfiguracionInicial::class)->assertActionHidden('mostrarBienvenida');

    Livewire::actingAs($administrador)->test(BienvenidaConfiguracion::class)->callAction('seguirDespues')->assertRedirect();
    expect(app(ConfiguracionInicialService::class)->bienvenidaApagada($administrador))->toBeTrue();

    Livewire::actingAs($administrador)->test(ConfiguracionInicial::class)->assertActionVisible('mostrarBienvenida')->callAction('mostrarBienvenida');
    expect(app(ConfiguracionInicialService::class)->bienvenidaApagada($administrador))->toBeFalse();
});

it('dentro de la ventana, un enlace abre su pantalla ahí mismo y la flecha vuelve; en el cierre, no', function () {
    $administrador = User::factory()->create()->assignRole(SeguridadService::ROL_ADMINISTRADOR);
    $persona = User::factory()->create();

    Livewire::actingAs($administrador)->test(BienvenidaConfiguracion::class)->call('ir', 'nucleo.usuarios')
        ->call('abrirEnPanel', '/seguridad/usuarios/'.$persona->id)->assertReturned(true)->assertSeeHtml('data-volver')
        ->call('volver')->assertSet('ficha', null)
        ->call('abrirEnPanel', '/seguridad/usuarios/'.$persona->id)->call('siguiente')->assertSet('ficha', null)
        ->call('ir', 'nucleo.ia')->call('siguiente')->call('abrirEnPanel', '/seguridad/usuarios/'.$persona->id)->assertReturned(false);
});

it('montada a mano, a quien no le toca nace cerrada y no muestra los pasos', function () {
    Livewire::actingAs(User::factory()->create())->test(BienvenidaConfiguracion::class)
        ->assertSet('cerrada', true)->assertDontSeeHtml('data-tq-bienvenida')->assertDontSee('Datos de la empresa');
});
