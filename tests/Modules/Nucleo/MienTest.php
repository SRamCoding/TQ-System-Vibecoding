<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Mien\ComponenteTarjetaMien;
use App\Modules\Nucleo\Mien\RegistroTarjetasMien;
use App\Modules\Nucleo\Mien\TarjetaMien;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Navegacion\AreaModulo;
use App\Modules\Nucleo\Navegacion\ColoresMarca;
use App\Modules\Nucleo\Navegacion\RegistroModulos;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Exceptions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/*
 * Plan 1.4l: «Mien», el tablero personal. El área «Personal» va antes que todo; la caja la ve toda persona con sesión;
 * cada módulo aporta sus tarjetas, visibles con su permiso, y cada una carga aparte y falla sola.
 */

final class TarjetaMienDePrueba extends ComponenteTarjetaMien
{
    protected function contenido(): View
    {
        return view()->file(__DIR__.'/vistas/tarjeta-mien-prueba.blade.php', ['texto' => 'Tres pendientes de prueba']);
    }
}

final class TarjetaMienQueFalla extends ComponenteTarjetaMien
{
    protected function contenido(): View
    {
        throw new RuntimeException('Falla de prueba');
    }
}

final class TarjetaMienQueFallaAlMontar extends ComponenteTarjetaMien
{
    public function mount(): void
    {
        throw new RuntimeException('Falla al montar');
    }

    protected function contenido(): View
    {
        return view()->file(__DIR__.'/vistas/tarjeta-mien-prueba.blade.php', ['texto' => 'No debería verse']);
    }
}

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    app()->instance(RegistroTarjetasMien::class, new RegistroTarjetasMien);
});

test('el área «Personal» va primero, con la caja «Mien» para toda persona y sus preguntas', function () {
    expect(AreaModulo::cases()[0])->toBe(AreaModulo::Personal)
        ->and(AreaModulo::Personal->color())->toBe('dorado');

    $mien = collect(app(RegistroModulos::class)->todas())->firstWhere('clave', 'mien');
    expect($mien?->permiso)->toBeNull()
        ->and($mien?->area)->toBe(AreaModulo::Personal)
        ->and($mien?->url)->toBe('/mien');

    test()->actingAs(User::factory()->create())->get('/')
        ->assertSeeInOrder(['data-area="personal"', 'data-modulo="mien"'], false)
        // Plan 1.4q, D1: las preguntas reemplazan a la nube con la descripción.
        ->assertSee('¿Qué tengo pendiente hoy?', false);
});

test('la página abre para toda persona con sesión; sin tarjetas, dice que llegarán con sus módulos', function () {
    test()->get('/mien')->assertRedirect('/login');

    test()->actingAs(User::factory()->create())->get('/mien')->assertOk()
        ->assertSee('Tu tablero todavía está vacío')
        ->assertSee('cuando lleguen sus módulos');
});

test('una tarjeta se ve con el permiso de su módulo y no sin él; se dibuja aparte, después de abrir', function () {
    Permission::findOrCreate('pruebas.ver', 'web');
    app(RegistroTarjetasMien::class)->registrar(new TarjetaMien('pendientes-prueba', 'Tus pendientes', 'pruebas.ver', TarjetaMienDePrueba::class));

    test()->actingAs(User::factory()->create())->get('/mien')->assertOk()
        ->assertDontSee('Tus pendientes')
        ->assertSee('Tu tablero todavía está vacío');

    $conPermiso = User::factory()->create();
    $conPermiso->givePermissionTo('pruebas.ver');
    test()->actingAs($conPermiso)->get('/mien')->assertOk()
        ->assertSee('Tus pendientes')
        // Carga diferida: al abrir, solo el título y el indicador; el contenido llega en su propia petición.
        ->assertDontSee('Tres pendientes de prueba');

    // Diferida: primero la espera, que pedirá la tarjeta por su nombre registrado.
    Livewire::actingAs($conPermiso)->test('mien.pendientes-prueba', ['titulo' => 'Tus pendientes', 'lazy' => true])
        ->assertSee('Tus pendientes')
        ->assertSeeHtml('wire:name="mien.pendientes-prueba"')
        ->assertSeeHtml('__lazyLoad')
        ->assertDontSee('Tres pendientes de prueba');

    // Ya cargada: su vista usa `$this` (se dibuja dentro de Livewire).
    Livewire::actingAs($conPermiso)->test('mien.pendientes-prueba', ['titulo' => 'Tus pendientes'])
        ->assertSee('Tres pendientes de prueba · Tus pendientes');
});

test('sin el permiso, la tarjeta no se monta aunque se la pida directamente (también diferida)', function () {
    Permission::findOrCreate('pruebas.ver', 'web');
    app(RegistroTarjetasMien::class)->registrar(new TarjetaMien('pendientes-prueba', 'Tus pendientes', 'pruebas.ver', TarjetaMienDePrueba::class));

    Livewire::actingAs(User::factory()->create())->test('mien.pendientes-prueba', ['titulo' => 'Tus pendientes'])
        ->assertStatus(403)
        ->assertDontSee('Tres pendientes de prueba');
});

test('una tarjeta que falla dice «No se pudo cargar», informa el error y las demás siguen', function () {
    Exceptions::fake();
    app(RegistroTarjetasMien::class)->registrar(new TarjetaMien('falla', 'La que falla', null, TarjetaMienQueFalla::class, orden: 1));
    app(RegistroTarjetasMien::class)->registrar(new TarjetaMien('sana', 'La sana', null, TarjetaMienDePrueba::class, orden: 2));

    app(RegistroTarjetasMien::class)->registrar(new TarjetaMien('falla-al-montar', 'La que falla al montar', null, TarjetaMienQueFallaAlMontar::class, orden: 3));
    $usuario = User::factory()->create();

    Livewire::actingAs($usuario)->test('mien.falla', ['titulo' => 'La que falla'])
        ->assertSee('La que falla')
        ->assertSee('No se pudo cargar');
    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Falla de prueba');

    Livewire::actingAs($usuario)->test('mien.falla-al-montar', ['titulo' => 'La que falla al montar'])
        ->assertSee('No se pudo cargar')
        ->assertDontSee('No debería verse');
    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Falla al montar');

    Livewire::actingAs($usuario)->test('mien.sana', ['titulo' => 'La sana'])->assertSee('Tres pendientes de prueba');
    test()->actingAs($usuario)->get('/mien')->assertOk()->assertSeeInOrder(['La que falla', 'La sana']);
});

test('las tarjetas salen por su orden y, a igual orden, por el de registro', function () {
    $registro = app(RegistroTarjetasMien::class);
    $registro->registrar(new TarjetaMien('c', 'C', null, TarjetaMienDePrueba::class, orden: 50));
    $registro->registrar(new TarjetaMien('a', 'A', null, TarjetaMienDePrueba::class, orden: 10));
    $registro->registrar(new TarjetaMien('b', 'B', null, TarjetaMienDePrueba::class, orden: 50));

    expect(array_map(fn (TarjetaMien $t): string => $t->clave, $registro->para(User::factory()->create())))->toBe(['a', 'c', 'b']);
});

test('clave repetida o mal formada, o un componente que no es de tarjeta, es un error al registrar', function () {
    $registro = app(RegistroTarjetasMien::class);
    $registro->registrar(new TarjetaMien('unica', 'Única', null, TarjetaMienDePrueba::class));

    expect(fn () => $registro->registrar(new TarjetaMien('unica', 'Otra', null, TarjetaMienDePrueba::class)))->toThrow(InvalidArgumentException::class, 'ya está registrada')
        ->and(fn () => new TarjetaMien('Con Espacios', 'X', null, TarjetaMienDePrueba::class))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new TarjetaMien('ajena', 'X', null, stdClass::class))->toThrow(InvalidArgumentException::class, 'ComponenteTarjetaMien');
});

test('el dorado se lee como texto en los dos modos (al menos 4,5:1)', function () {
    $luminancia = function (string $hex): float {
        $canales = array_map(fn (string $c): float => hexdec($c) / 255, str_split(ltrim($hex, '#'), 2));
        $lineal = array_map(fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, $canales);

        return 0.2126 * $lineal[0] + 0.7152 * $lineal[1] + 0.0722 * $lineal[2];
    };
    $contraste = fn (string $a, string $b): float => (max($luminancia($a), $luminancia($b)) + 0.05) / (min($luminancia($a), $luminancia($b)) + 0.05);

    // Modo claro: el fondo de la página (gris 50) y el del ítem abierto de la barra (gris 100). Oscuro: el de la página
    // (gris 950) y el de las tarjetas (gris 900).
    expect($contraste(ColoresMarca::DORADO, '#FAFAFA'))->toBeGreaterThanOrEqual(4.5)
        ->and($contraste(ColoresMarca::DORADO, '#F4F4F5'))->toBeGreaterThanOrEqual(4.5)
        ->and($contraste(ColoresMarca::DORADO_OSCURO, '#09090B'))->toBeGreaterThanOrEqual(4.5)
        ->and($contraste(ColoresMarca::DORADO_OSCURO, '#18181B'))->toBeGreaterThanOrEqual(4.5)
        ->and(ColoresMarca::paletaDorada()[600])->toBe(ColoresMarca::DORADO)
        ->and(ColoresMarca::paletaDorada()[400])->toBe(ColoresMarca::DORADO_OSCURO);
});
