<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\CambiarMiClave;
use App\Modules\Nucleo\Acciones\PedirCodigoClave;
use App\Modules\Nucleo\Acciones\RenovarMiClave;
use App\Modules\Nucleo\database\factories\UserFactory;
use App\Modules\Nucleo\Excepciones\ClaveRechazada;
use App\Modules\Nucleo\Filament\Pages\RenovarClave;
use App\Modules\Nucleo\Http\ExigirClaveRenovada;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Notificaciones\CodigoCambioClave;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\ClaveService;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * Plan 1.4h, D3 y D5: la regla de la contraseña, el cambio con código por correo y la renovación obligatoria de las
 * que no cumplen la regla.
 */

beforeEach(function () {
    Notification::fake();
    Filament::setCurrentPanel('app');
});

function claves(): ClaveService
{
    return app(ClaveService::class);
}

/** El código que llegó al correo de la persona en la última notificación. */
function codigoDelCorreo(User $usuario): string
{
    $codigo = null;
    Notification::assertSentTo($usuario, CodigoCambioClave::class, function (CodigoCambioClave $aviso) use (&$codigo): bool {
        $codigo = $aviso->codigo();

        return true;
    });

    return (string) $codigo;
}

test('la regla: al menos 8 caracteres, mayúscula, minúscula, número y carácter especial; la misma al restablecerla', function (string $clave, bool $vale) {
    expect(ClaveService::faltas($clave) === [])->toBe($vale)
        ->and(Validator::make(['c' => $clave], ['c' => [Password::default()]])->passes())->toBe($vale);
})->with([
    'cumple' => ['Clave-segura1', true],
    'corta' => ['Cl-1a', false],
    'sin mayúscula' => ['clave-segura1', false],
    'sin número' => ['Clave-segura', false],
    'sin carácter especial' => ['ClaveSegura1', false],
    'la de antes de las pruebas' => ['password', false],
]);

test('las acciones de la contraseña se registran sin permiso: son de la propia cuenta', function () {
    $registro = app(RegistroAcciones::class);

    expect($registro->obtener('nucleo.pedir-codigo-clave'))->toBeInstanceOf(PedirCodigoClave::class)
        ->and($registro->obtener('nucleo.cambiar-mi-clave'))->toBeInstanceOf(CambiarMiClave::class)
        ->and($registro->obtener('nucleo.renovar-mi-clave'))->toBeInstanceOf(RenovarMiClave::class)
        ->and($registro->obtener('nucleo.cambiar-mi-clave')->permiso())->toBeNull();
});

test('el código llega al correo de la persona, que se muestra en parte oculto', function () {
    $usuario = User::factory()->create(['email' => 'sebastian@techquk.test']);

    $resultado = app(EjecutorAcciones::class)->ejecutar('nucleo.pedir-codigo-clave', [], $usuario);

    expect($resultado)->toBe(['correo' => 'se***@techquk.test'])
        ->and(codigoDelCorreo($usuario))->toMatch('/^\d{6}$/');
});

test('con el código se cambia la contraseña; el código sirve una sola vez', function () {
    $usuario = User::factory()->create();
    claves()->pedirCodigo($usuario);
    $codigo = codigoDelCorreo($usuario);

    app(EjecutorAcciones::class)->ejecutar('nucleo.cambiar-mi-clave', ['codigo' => $codigo, 'clave' => 'Nueva-clave2', 'repetida' => 'Nueva-clave2'], $usuario);

    $usuario->refresh();
    expect(Hash::check('Nueva-clave2', $usuario->password))->toBeTrue()
        ->and($usuario->clave_cambiada_at)->not->toBeNull()
        ->and(fn () => claves()->cambiarConCodigo($usuario, $codigo, 'Otra-clave3', 'Otra-clave3'))->toThrow(ClaveRechazada::class, 'El código venció o ya se usó');
});

test('un código equivocado descuenta intentos y al quinto se anula', function () {
    $usuario = User::factory()->create();
    claves()->pedirCodigo($usuario);
    $codigo = codigoDelCorreo($usuario);
    $malo = $codigo === '000000' ? '111111' : '000000';

    foreach ([4, 3, 2, 1] as $quedan) {
        expect(fn () => claves()->cambiarConCodigo($usuario, $malo, 'Nueva-clave2', 'Nueva-clave2'))->toThrow(ClaveRechazada::class, "Te quedan {$quedan} intentos");
    }

    expect(fn () => claves()->cambiarConCodigo($usuario, $malo, 'Nueva-clave2', 'Nueva-clave2'))->toThrow(ClaveRechazada::class, 'se anuló')
        ->and(fn () => claves()->cambiarConCodigo($usuario, $codigo, 'Nueva-clave2', 'Nueva-clave2'))->toThrow(ClaveRechazada::class, 'venció o ya se usó');
});

test('el código vence a los 10 minutos, aunque haya intentos fallidos en medio', function () {
    $usuario = User::factory()->create();
    claves()->pedirCodigo($usuario);
    $codigo = codigoDelCorreo($usuario);

    $this->travel(9)->minutes();
    expect(fn () => claves()->cambiarConCodigo($usuario, $codigo === '000000' ? '111111' : '000000', 'Nueva-clave2', 'Nueva-clave2'))->toThrow(ClaveRechazada::class);

    $this->travel(2)->minutes();
    expect(fn () => claves()->cambiarConCodigo($usuario, $codigo, 'Nueva-clave2', 'Nueva-clave2'))->toThrow(ClaveRechazada::class, 'venció o ya se usó');
});

test('otro código, como mucho uno por minuto; el nuevo anula el anterior', function () {
    $usuario = User::factory()->create();
    claves()->pedirCodigo($usuario);
    $primero = codigoDelCorreo($usuario);

    expect(fn () => claves()->pedirCodigo($usuario))->toThrow(ClaveRechazada::class, 'Espera');

    $this->travel(61)->seconds();
    Notification::fake();
    claves()->pedirCodigo($usuario);
    $segundo = codigoDelCorreo($usuario);

    if ($primero !== $segundo) {
        expect(fn () => claves()->cambiarConCodigo($usuario, $primero, 'Nueva-clave2', 'Nueva-clave2'))->toThrow(ClaveRechazada::class);
    }

    claves()->cambiarConCodigo($usuario, $segundo, 'Nueva-clave2', 'Nueva-clave2');
    expect(Hash::check('Nueva-clave2', $usuario->refresh()->password))->toBeTrue();
});

test('la nueva debe cumplir la regla, coincidir y ser distinta de la actual; si no, el código no se gasta', function (string $clave, string $repetida, string $mensaje) {
    $usuario = User::factory()->create();
    claves()->pedirCodigo($usuario);
    $codigo = codigoDelCorreo($usuario);

    expect(fn () => claves()->cambiarConCodigo($usuario, $codigo, $clave, $repetida))->toThrow(ClaveRechazada::class, $mensaje);

    claves()->cambiarConCodigo($usuario, $codigo, 'Nueva-clave2', 'Nueva-clave2');
})->with([
    'no cumple' => ['clave-debil', 'clave-debil', 'mayúscula'],
    'no coincide' => ['Nueva-clave2', 'Nueva-clave3', 'no coinciden'],
    'igual a la actual' => [UserFactory::CLAVE, UserFactory::CLAVE, 'distinta de la actual'],
]);

test('la contraseña nunca va a la bitácora: solo que se cambió y cuándo', function () {
    $usuario = User::factory()->create();
    claves()->pedirCodigo($usuario);
    claves()->cambiarConCodigo($usuario, codigoDelCorreo($usuario), 'Nueva-clave2', 'Nueva-clave2');

    $cambios = AuditoriaCambio::query()->where('auditable_id', $usuario->id)->get();

    expect($cambios->contains(fn (AuditoriaCambio $c): bool => array_key_exists('password', $c->valores_nuevos ?? [])))->toBeFalse()
        ->and($cambios->contains(fn (AuditoriaCambio $c): bool => str_contains((string) json_encode($c->valores_nuevos), 'Nueva-clave2')))->toBeFalse();
});

test('quien entra con una contraseña que no cumple queda marcado; con una que cumple, no', function () {
    $debil = User::factory()->create(['email' => 'debil@techquk.test', 'password' => 'password']);
    $fuerte = User::factory()->create(['email' => 'fuerte@techquk.test']);

    Livewire::test(Login::class)->fillForm(['email' => 'debil@techquk.test', 'password' => 'password'])->call('authenticate');
    auth()->logout();
    Livewire::test(Login::class)->fillForm(['email' => 'fuerte@techquk.test', 'password' => UserFactory::CLAVE])->call('authenticate');

    expect($debil->refresh()->debe_cambiar_clave)->toBeTrue()
        ->and($fuerte->refresh()->debe_cambiar_clave)->toBeFalse();
});

test('con la marca, toda página lleva a «Cambia tu contraseña»; sin ella, esa pantalla no se abre', function () {
    $marcado = User::factory()->create(['debe_cambiar_clave' => true]);

    test()->actingAs($marcado)->get('/')->assertRedirect(RenovarClave::getUrl());
    test()->actingAs($marcado)->get('/mis-preferencias')->assertRedirect(RenovarClave::getUrl());
    test()->actingAs($marcado)->get(RenovarClave::getUrl())->assertOk()->assertSee('Cambia tu contraseña');

    test()->actingAs(User::factory()->create())->get(RenovarClave::getUrl())->assertForbidden();
});

test('el bloqueo también corre en las peticiones de Livewire: es persistente', function () {
    expect(Livewire::getPersistentMiddleware())->toContain(ExigirClaveRenovada::class);
});

test('con la marca, una petición de Livewire de otro componente se rechaza, aunque venga de la misma pantalla', function () {
    test()->actingAs(User::factory()->create(['debe_cambiar_clave' => true]));
    $nombre = app('livewire.finder')->normalizeName(RenovarClave::class);
    $peticion = fn (array $nombres): Request => tap(
        // Así la arma Livewire al reaplicar el middleware: con la ruta de la página de origen.
        Request::create(RenovarClave::getUrl(), 'POST', ['components' => array_map(fn (string $n): array => ['snapshot' => json_encode(['memo' => ['name' => $n]])], $nombres)]),
        fn (Request $r) => $r->headers->set('X-Livewire', '1'),
    );

    expect(fn () => app(ExigirClaveRenovada::class)->handle($peticion(['filament.livewire.topbar']), fn () => response('ok')))->toThrow(HttpException::class)
        ->and(fn () => app(ExigirClaveRenovada::class)->handle($peticion([$nombre, 'filament.livewire.global-search']), fn () => response('ok')))->toThrow(HttpException::class)
        ->and(app(ExigirClaveRenovada::class)->handle($peticion([$nombre]), fn () => response('ok'))->getContent())->toBe('ok');
});

test('al cambiarla, esta sesión sigue y otra con la contraseña vieja sale al ingreso', function () {
    $usuario = User::factory()->create(['debe_cambiar_clave' => true]);
    $guardia = Filament::auth();
    $vieja = $guardia instanceof SessionGuard ? $guardia->hashPasswordForCookie((string) $usuario->password) : (string) $usuario->password;
    $tokenViejo = $usuario->remember_token;

    Livewire::actingAs($usuario)->test(RenovarClave::class)
        ->fillForm(['clave' => 'Nueva-clave2', 'repetida' => 'Nueva-clave2'])
        ->call('guardar');

    // Esta sesión guardó el resumen de la nueva; otra, con el de la vieja, se cierra.
    expect(session('password_hash_web'))->not->toBe($vieja)
        ->and($usuario->refresh()->remember_token)->not->toBe($tokenViejo);

    test()->actingAs($usuario)->withSession(['password_hash_web' => session('password_hash_web')])->get('/')->assertOk();
    test()->actingAs($usuario)->withSession(['password_hash_web' => $vieja])->get('/')->assertRedirect('/login');
});

test('restablecerla por correo, o entrar con una que cumple, quita la marca', function () {
    $marcado = User::factory()->create(['debe_cambiar_clave' => true]);
    event(new PasswordReset($marcado));

    expect($marcado->refresh()->debe_cambiar_clave)->toBeFalse()
        ->and($marcado->clave_cambiada_at)->not->toBeNull();

    $otro = User::factory()->create(['email' => 'otro@techquk.test', 'debe_cambiar_clave' => true]);
    Livewire::test(Login::class)->fillForm(['email' => 'otro@techquk.test', 'password' => UserFactory::CLAVE])->call('authenticate');

    expect($otro->refresh()->debe_cambiar_clave)->toBeFalse();
});

test('renovarla quita la marca y lleva al inicio; sin la marca, renovar no procede', function () {
    $usuario = User::factory()->create(['debe_cambiar_clave' => true]);

    Livewire::actingAs($usuario)->test(RenovarClave::class)
        ->fillForm(['clave' => 'Nueva-clave2', 'repetida' => 'Nueva-clave2'])
        ->call('guardar')
        ->assertRedirect(Filament::getUrl());

    $usuario->refresh();
    expect($usuario->debe_cambiar_clave)->toBeFalse()
        ->and(Hash::check('Nueva-clave2', $usuario->password))->toBeTrue()
        ->and(fn () => claves()->renovar($usuario, 'Otra-clave3', 'Otra-clave3'))->toThrow(ClaveRechazada::class, 'ya cumple la regla');
});

test('al renovar con una contraseña que no cumple, se avisa y la marca sigue', function () {
    $usuario = User::factory()->create(['debe_cambiar_clave' => true]);

    Livewire::actingAs($usuario)->test(RenovarClave::class)
        ->fillForm(['clave' => 'debil', 'repetida' => 'debil'])
        ->call('guardar')
        ->assertNotified();

    expect($usuario->refresh()->debe_cambiar_clave)->toBeTrue();
});
