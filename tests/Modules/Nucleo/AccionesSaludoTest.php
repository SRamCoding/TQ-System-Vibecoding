<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\ElementoCatalogoRechazado;
use App\Modules\Nucleo\Excepciones\PreferenciaRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Filament\Resources\Usuarios\Pages\VerUsuario;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\PreferenciaUsuario;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\PreferenciasUsuarioService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
 * Plan 1.4b, paso 5: cumpleaños por acciones registradas, sin pantalla, con sus rechazos. Las preferencias del saludo salieron en
 * la 1.4aa (D5); queda la prueba del guardado de una preferencia que ya tiene fila.
 * La ficha del usuario, al final, solo llama a la acción con la versión leída al abrir.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

/** @param  array<string, mixed>  $datos */
function accionSaludo(string $clave, array $datos, User $usuario): mixed
{
    return app(EjecutorAcciones::class)->ejecutar($clave, $datos, $usuario);
}

function gestorDeUsuarios(): User
{
    return User::factory()->create()->givePermissionTo('usuarios.gestionar');
}

test('el núcleo registra las acciones: las de la propia persona sin permiso, la de otra con usuarios.gestionar', function () {
    $registro = app(RegistroAcciones::class);

    expect($registro->obtener('nucleo.registrar-mi-cumpleanos')->permiso())->toBeNull()
        ->and($registro->obtener('nucleo.seguridad.registrar-cumpleanos-usuario')->permiso())->toBe('usuarios.gestionar');
});

// --- Mi cumpleaños ---

test('cada persona registra y borra su cumpleaños, que sube la versión de la cuenta', function () {
    $ana = User::factory()->create();

    accionSaludo('nucleo.registrar-mi-cumpleanos', ['cumpleanos_dia' => 29, 'cumpleanos_mes' => 2], $ana);
    expect($ana->refresh()->cumpleanos_dia)->toBe(29)
        ->and($ana->cumpleanos_mes)->toBe(2)
        ->and($ana->version)->toBe(2);

    accionSaludo('nucleo.registrar-mi-cumpleanos', ['cumpleanos_dia' => null, 'cumpleanos_mes' => null], $ana);
    expect($ana->refresh()->cumpleanos_dia)->toBeNull()
        ->and($ana->cumpleanos_mes)->toBeNull();
});

test('el cumpleaños no deja rastro en la bitácora', function () {
    $ana = User::factory()->create();

    accionSaludo('nucleo.registrar-mi-cumpleanos', ['cumpleanos_dia' => 9, 'cumpleanos_mes' => 3], $ana);

    expect(AuditoriaCambio::query()->where('auditable_type', 'user')->where('auditable_id', $ana->id)->where('evento', 'actualizado')->exists())->toBeFalse();
});

test('se rechaza un cumpleaños incompleto o que no existe, sin repetir la fecha en el mensaje', function (array $datos, string $mensaje) {
    $ana = User::factory()->create();

    expect(fn () => accionSaludo('nucleo.registrar-mi-cumpleanos', $datos, $ana))->toThrow(PreferenciaRechazada::class, $mensaje)
        ->and($ana->refresh()->cumpleanos_dia)->toBeNull();
})->with([
    'solo el día' => [['cumpleanos_dia' => 15, 'cumpleanos_mes' => null], 'Escribe el día y el mes'],
    'solo el mes' => [['cumpleanos_dia' => null, 'cumpleanos_mes' => 6], 'Escribe el día y el mes'],
    '31 de abril' => [['cumpleanos_dia' => 31, 'cumpleanos_mes' => 4], 'Ese día no existe en ese mes.'],
    '30 de febrero' => [['cumpleanos_dia' => 30, 'cumpleanos_mes' => 2], 'Ese día no existe en ese mes.'],
]);

test('omitir el cumpleaños o salirse del rango falla en la validación: no lo borra', function (array $datos) {
    $ana = User::factory()->create();
    accionSaludo('nucleo.registrar-mi-cumpleanos', ['cumpleanos_dia' => 9, 'cumpleanos_mes' => 3], $ana);

    expect(fn () => accionSaludo('nucleo.registrar-mi-cumpleanos', $datos, $ana))->toThrow(ValidationException::class)
        ->and($ana->refresh()->cumpleanos_dia)->toBe(9);
})->with([
    'sin campos' => [[]],
    'sin el mes' => [['cumpleanos_dia' => 9]],
    'mes 13' => [['cumpleanos_dia' => 1, 'cumpleanos_mes' => 13]],
    'día 32' => [['cumpleanos_dia' => 32, 'cumpleanos_mes' => 1]],
]);

test('el usuario de sistema no tiene cumpleaños', function () {
    expect(fn () => accionSaludo('nucleo.registrar-mi-cumpleanos', ['cumpleanos_dia' => 1, 'cumpleanos_mes' => 1], User::query()->findOrFail(app(UsuarioSistema::class)->id())))
        ->toThrow(AccionRechazada::class);
});

// --- Cumpleaños de otra persona ---

test('quien gestiona usuarios registra el cumpleaños de otra persona con la versión leída', function () {
    $ana = User::factory()->create();

    accionSaludo('nucleo.seguridad.registrar-cumpleanos-usuario', ['usuario_id' => $ana->id, 'cumpleanos_dia' => 18, 'cumpleanos_mes' => 12, 'version' => 1], gestorDeUsuarios());

    expect($ana->refresh()->cumpleanos_dia)->toBe(18)
        ->and(fn () => accionSaludo('nucleo.seguridad.registrar-cumpleanos-usuario', ['usuario_id' => $ana->id, 'cumpleanos_dia' => 1, 'cumpleanos_mes' => 1, 'version' => 1], gestorDeUsuarios()))
        ->toThrow(VersionDesactualizada::class)
        ->and(fn () => accionSaludo('nucleo.seguridad.registrar-cumpleanos-usuario', ['usuario_id' => $ana->id, 'cumpleanos_dia' => 1, 'cumpleanos_mes' => 1, 'version' => 2], User::factory()->create()))
        ->toThrow(AccionRechazada::class)
        ->and($ana->refresh()->cumpleanos_mes)->toBe(12);
});

// --- Pantalla ---

test('la ficha del usuario muestra el cumpleaños y lo registra con la versión leída al abrir', function () {
    Filament::setCurrentPanel('app');
    test()->actingAs(User::factory()->create()->givePermissionTo(['usuarios.ver', 'usuarios.gestionar']));
    $ana = User::factory()->create();

    Livewire::test(VerUsuario::class, ['record' => $ana->getRouteKey()])
        ->assertSee('Sin registrar')
        ->callAction('nucleoSeguridadRegistrarCumpleanosUsuario', ['cumpleanos_dia' => 28, 'cumpleanos_mes' => 7])
        ->assertHasNoActionErrors();

    expect($ana->refresh()->cumpleanos_dia)->toBe(28)
        ->and($ana->cumpleanos_mes)->toBe(7);

    Livewire::test(VerUsuario::class, ['record' => $ana->getRouteKey()])->assertSee('28 de julio');
});

test('el menú del usuario ya no ofrece «Mi saludo» ni «Mi cumpleaños»: pasaron a «Mis preferencias» y «Mis datos» (planes 1.4g y 1.4h)', function () {
    test()->actingAs(User::factory()->create());

    test()->get('/')->assertOk()->assertDontSee('Mi saludo')->assertDontSee('Mi cumpleaños')->assertSee('Mis datos');
});

// --- Revisión de arquitectura de la 1.4b ---

test('si la fila de la preferencia ya existe (otro guardado llegó antes), el guardado la actualiza sin chocar', function () {
    $ana = User::factory()->create();
    PreferenciaUsuario::query()->create(['user_id' => $ana->id, 'clave' => 'apariencia.estacion', 'valor' => 'false']);

    accionSaludo('nucleo.cambiar-preferencia', ['clave' => 'apariencia.estacion', 'valor' => 'true'], $ana);
    accionSaludo('nucleo.cambiar-preferencia', ['clave' => 'apariencia.ambientacion', 'valor' => 'false'], $ana);
    accionSaludo('nucleo.cambiar-preferencia', ['clave' => 'apariencia.ambientacion', 'valor' => 'false'], $ana);

    // Volver al defecto (estación prendida) borra la fila; apagar la ambientación deja una sola.
    expect(PreferenciaUsuario::query()->where('user_id', $ana->id)->pluck('valor', 'clave')->all())->toBe(['apariencia.ambientacion' => 'false'])
        ->and(app(PreferenciasUsuarioService::class)->siNo($ana, 'apariencia.ambientacion'))->toBeFalse();
});

test('una frase mal formada en el catálogo se rechaza con un mensaje claro, no con un error de la base', function (array $datos, string $mensaje) {
    $editor = User::factory()->create()->givePermissionTo('catalogos.editar');
    $base = ['codigo' => 'prueba-'.fake()->unique()->lexify('????'), 'tipo' => 'frase', 'categoria_frase_id' => null, 'idioma' => 'es', 'texto' => 'Algo', 'fuente' => null, 'momento' => null, 'fuera_de_horario' => null, 'sesion' => null, 'dia_semana' => null, 'area' => null, 'ocasion' => null];

    expect(fn () => accionSaludo('nucleo.frases-saludo.crear', [...$base, ...$datos], $editor))
        ->toThrow(ElementoCatalogoRechazado::class, $mensaje);
})->with([
    'frase sin categoría' => [[], 'Una frase necesita su categoría.'],
    'saludo sin {nombre}' => [['tipo' => 'saludo', 'texto' => 'Hola'], 'Un saludo lleva {nombre}'],
    'agradecimiento sin {anios}' => [['tipo' => 'agradecimiento', 'texto' => 'Gracias a Dios'], 'Un agradecimiento lleva {anios}'],
    'frase con sesión' => [['sesion' => 'vuelve'], 'Solo un saludo lleva sesión'],
    'saludo de sesión con fecha especial' => [['tipo' => 'saludo', 'texto' => 'De vuelta, {nombre}', 'sesion' => 'vuelve', 'ocasion' => 'navidad'], 'Solo un saludo lleva sesión'],
    'agradecimiento largo' => [['tipo' => 'agradecimiento', 'texto' => 'Gracias a Dios por {anios} años de camino, de trabajo y de bendición'], 'no pasa de 60 letras'],
]);
