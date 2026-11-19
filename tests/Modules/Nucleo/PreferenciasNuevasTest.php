<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\factories\UserFactory;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Filament\Apariencia;
use App\Modules\Nucleo\Filament\GuardarTema;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\CajaModulo;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use App\Modules\Nucleo\Preferencias\DefinicionPreferencia;
use App\Modules\Nucleo\Preferencias\GrupoPreferencia;
use App\Modules\Nucleo\Preferencias\RegistroPreferencias;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Js;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/*
 * Plan 1.4g, D4 y D5: tema, tamaño de letra, densidad de las tablas, reducir animaciones y pantalla al entrar. Que el
 * navegador lo pinte bien lo revisa el desarrollador a la vista; aquí, lo que la página trae y lo que se guarda.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    Permission::findOrCreate('ventas.ver', 'web');

    // Solo las cajas de prueba: las del Núcleo («Mien», que ve todo el mundo) se prueban en MienTest.
    app()->instance(RegistroModulos::class, new RegistroModulos);
    $registro = app(RegistroModulos::class);
    $registro->registrar(new CajaModulo('agenda', 'Agenda', '/agenda', permiso: null, area: AreaModulo::Erp));
    $registro->registrar(new CajaModulo('ventas', 'Ventas', '/ventas', permiso: 'ventas.ver', area: AreaModulo::Crm));
});

function cambiarPreferenciaNueva(User $usuario, string $clave, mixed $valor): void
{
    app(PreferenciasUsuarioService::class)->cambiar($usuario, $clave, $valor);
}

function paginaConApariencia(User $usuario, string $url = '/'): string
{
    return (string) test()->actingAs($usuario)->followingRedirects()->get($url)->assertOk()->getContent();
}

test('Apariencia trae tema, letra, densidad, animaciones, la estación de las cajas y la ambientación de la fecha, en ese orden y con defectos para quien nunca las cambia', function () {
    $apariencia = array_map(
        fn (DefinicionPreferencia $definicion): array => [$definicion->nombre, $definicion->defecto, array_keys($definicion->opcionesPara(User::factory()->create()))],
        app(RegistroPreferencias::class)->delGrupo(GrupoPreferencia::Apariencia),
    );

    expect($apariencia)->toBe([
        ['tema', 'system', ['system', 'light', 'dark']],
        ['letra', 'normal', ['normal', 'grande']],
        ['densidad', 'comodo', ['comodo', 'compacto']],
        ['animaciones', 'sistema', ['sistema', 'reducir']],
        // Plan 1.4r, D5: prendida por defecto.
        ['estacion', true, []],
        // Plan 1.4aa, D5: viene del grupo Saludo, con el mismo defecto.
        ['ambientacion', true, []],
    ]);
});

test('sin cambiar nada, la página no agrega clases y deja el tema según el sistema', function () {
    expect(paginaConApariencia(User::factory()->create()))->toContain('aplicar('.Js::from([]).', '.Js::from('system').')');
});

test('lo elegido llega del servidor antes de dibujar: clases en <html> y el tema para Filament', function () {
    $usuario = User::factory()->create();
    cambiarPreferenciaNueva($usuario, 'apariencia.tema', 'dark');
    cambiarPreferenciaNueva($usuario, 'apariencia.letra', 'grande');
    cambiarPreferenciaNueva($usuario, 'apariencia.densidad', 'compacto');
    cambiarPreferenciaNueva($usuario, 'apariencia.animaciones', 'reducir');

    expect(Apariencia::clases($usuario))->toBe(['tq-letra-grande', 'tq-compacto', 'tq-sin-animaciones'])
        ->and(paginaConApariencia($usuario))->toContain('aplicar('.Js::from(['tq-letra-grande', 'tq-compacto', 'tq-sin-animaciones']).', '.Js::from('dark').')');
});

test('Filament sigue guardando el tema en el navegador con la clave «theme» y avisando con «theme-changed»', function () {
    // Si una actualización de Filament lo cambia, el tema vuelve a vivir solo en el navegador: esta prueba lo avisa.
    $html = paginaConApariencia(User::factory()->create());

    expect($html)->toContain("window.theme = localStorage.getItem('theme')")
        ->toContain("\$dispatch('theme-changed', theme)")
        ->toContain("localStorage.setItem('theme', tema)")
        ->toContain('x-on:theme-changed.window=');
});

test('el tema elegido con el selector de Filament se guarda en la base, y un valor raro no', function () {
    $usuario = User::factory()->create();

    Livewire::actingAs($usuario)->test(GuardarTema::class)->call('guardar', 'dark')->call('guardar', 'violeta');

    expect(Apariencia::tema($usuario))->toBe('dark');
});

test('la letra grande cambia la raíz; la densidad compacta, solo las celdas y encabezados de las tablas', function () {
    expect(paginaConApariencia(User::factory()->create()))
        ->toContain('html.tq-letra-grande { font-size: 112.5%; }')
        ->toContain("html.tq-compacto .fi-ta-cell,\n    html.tq-compacto .fi-ta-header-cell { --spacing: 0.15rem; }");
});

test('Filament sigue usando las dos clases de tabla de la densidad, y calcula sus rellenos con --spacing', function () {
    // Excepción de interfaz.md: si una actualización de Filament las renombra o deja la variable, la densidad compacta
    // deja de verse, sin romper nada.
    $html = paginaConApariencia(User::factory()->create()->givePermissionTo('catalogos.ver'), '/configuracion');
    $css = (string) file_get_contents(public_path('css/filament/filament/app.css'));

    expect($html)->toMatch('/class="[^"]*\bfi-ta-header-cell\b/')
        ->toMatch('/class="[^"]*\bfi-ta-cell\b/')
        ->and($css)->toContain('.fi-ta-header-cell{padding-inline:calc(var(--spacing) *')
        ->toContain('.fi-ta-text:not(.fi-inline){padding-inline:calc(var(--spacing) *');
});

test('toda animación nuestra respeta «reducir movimiento» del sistema y «reducir animaciones»', function () {
    // Las vistas de todos los módulos: una que llegue animada con otro módulo también se revisa.
    $vistas = base_path('app/Modules/Nucleo/resources/views');
    $faltan = [];
    $archivos = [];

    foreach (glob(base_path('app/Modules/*/resources/views'), GLOB_ONLYDIR) ?: [] as $carpeta) {
        $archivos = [...$archivos, ...iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($carpeta, FilesystemIterator::SKIP_DOTS)), false)];
    }

    expect($archivos)->not->toBe([]);

    foreach ($archivos as $archivo) {
        if (! $archivo instanceof SplFileInfo || ! str_ends_with($archivo->getFilename(), '.blade.php')) {
            continue;
        }

        $contenido = (string) file_get_contents($archivo->getPathname());

        if (preg_match('/@keyframes|animation:/', $contenido) !== 1) {
            continue;
        }

        // Los dibujos de la ambientación del núcleo van dentro de `.tq-amb`: los cubre su hoja común.
        $vista = str_contains($archivo->getPathname(), $vistas.'/filament/ambientacion/') ? "{$vistas}/filament/ambientacion/estilos.blade.php" : $archivo->getPathname();
        $reglas = (string) file_get_contents($vista);

        if (! str_contains($reglas, 'prefers-reduced-motion: reduce') || ! str_contains($reglas, 'html.tq-sin-animaciones')) {
            $faltan[] = str_replace(base_path().'/', '', $archivo->getPathname());
        }
    }

    expect($faltan)->toBe([]);
});

test('al iniciar sesión se llega a la pantalla elegida', function () {
    $usuario = User::factory()->create(['email' => 'pantalla@techquk.test']);
    cambiarPreferenciaNueva($usuario, 'navegacion.pantalla_inicio', 'agenda');

    Livewire::test(Login::class)
        ->fillForm(['email' => 'pantalla@techquk.test', 'password' => UserFactory::CLAVE])
        ->call('authenticate')
        ->assertRedirect('/agenda');
});

test('sin preferencia, o si perdió el permiso del módulo elegido, se llega al inicio', function () {
    $sinCambiar = User::factory()->create(['email' => 'nada@techquk.test']);
    $sinPermiso = User::factory()->create(['email' => 'sinpermiso@techquk.test'])->givePermissionTo('ventas.ver');
    cambiarPreferenciaNueva($sinPermiso, 'navegacion.pantalla_inicio', 'ventas');
    $sinPermiso->revokePermissionTo('ventas.ver');

    foreach (['nada@techquk.test', 'sinpermiso@techquk.test'] as $correo) {
        auth()->logout();

        Livewire::test(Login::class)
            ->fillForm(['email' => $correo, 'password' => UserFactory::CLAVE])
            ->call('authenticate')
            ->assertRedirect(Filament::getUrl());
    }

    expect($sinCambiar->exists)->toBeTrue();
});

test('si venía de un enlace concreto, gana el enlace', function () {
    $usuario = User::factory()->create(['email' => 'enlace@techquk.test']);
    cambiarPreferenciaNueva($usuario, 'navegacion.pantalla_inicio', 'agenda');
    session()->put('url.intended', url('/configuracion/parametros'));

    Livewire::test(Login::class)
        ->fillForm(['email' => 'enlace@techquk.test', 'password' => UserFactory::CLAVE])
        ->call('authenticate')
        ->assertRedirect(url('/configuracion/parametros'));
});

test('las opciones de la pantalla al entrar son el inicio y los módulos permitidos de hoy', function () {
    $definicion = app(RegistroPreferencias::class)->definicion('navegacion.pantalla_inicio');

    expect(array_keys($definicion?->opcionesPara(User::factory()->create()) ?? []))->toBe(['inicio', 'agenda'])
        ->and(array_keys($definicion?->opcionesPara(User::factory()->create()->givePermissionTo('ventas.ver')) ?? []))->toBe(['inicio', 'ventas', 'agenda']);
});
