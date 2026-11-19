<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;

/*
 * `seguridad:primer-administrador` (plan 1.4, D8): crea la primera cuenta con el rol `administrador`, a nombre del
 * usuario de sistema, y se niega si ya hay un administrador activo. Persona inventada.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
});

/** @param array<string, string> $opciones */
function primerAdministrador(array $opciones = [], string $clave = 'Una-clave-larga-1', ?string $repetida = null): PendingCommand
{
    return test()->artisan('seguridad:primer-administrador', [
        '--documento' => '01234567', '--nombres' => 'Ana', '--apellidos' => 'Pérez Soto', '--email' => 'ana@techquk.test', ...$opciones,
    ])
        ->expectsQuestion('Clave (al menos 12 caracteres, con mayúscula, minúscula, número y carácter especial)', $clave)
        ->expectsQuestion('Repite la clave', $repetida ?? $clave);
}

test('crea la persona, su cuenta con su clave y el rol administrador, a nombre del usuario de sistema', function () {
    primerAdministrador()->expectsOutput('Administrador creado: ana@techquk.test.')->assertSuccessful();

    $ana = User::query()->where('email', 'ana@techquk.test')->sole();

    expect($ana->name)->toBe('Pérez Soto Ana')
        ->and($ana->party?->numero_documento)->toBe('01234567')
        ->and($ana->hasRole(SeguridadService::ROL_ADMINISTRADOR))->toBeTrue()
        ->and($ana->can('usuarios.gestionar'))->toBeTrue()
        ->and(Hash::check('Una-clave-larga-1', $ana->password))->toBeTrue()
        ->and($ana->created_by)->toBe(app(UsuarioSistema::class)->id());
});

test('usa la persona que ya está registrada con ese documento', function () {
    $persona = Party::factory()->create(['numero_documento' => '01234567', 'nombres' => 'Ana', 'apellidos' => 'Pérez Soto', 'razon_social' => 'Pérez Soto Ana']);

    primerAdministrador()->assertSuccessful();

    expect(User::query()->where('email', 'ana@techquk.test')->sole()->party_id)->toBe($persona->id)
        ->and(Party::query()->where('numero_documento', '01234567')->count())->toBe(1);
});

test('se niega si ya hay un administrador activo', function () {
    primerAdministrador()->assertSuccessful();

    test()->artisan('seguridad:primer-administrador')
        ->expectsOutput('Ya hay un administrador activo: el siguiente lo crea él, desde «Usuarios».')
        ->assertFailed();

    expect(User::query()->where('es_sistema', false)->count())->toBe(1);
});

test('si algo falla no deja nada a medias: ni persona ni cuenta', function (array $opciones, string $clave, ?string $repetida, string $mensaje) {
    primerAdministrador($opciones, $clave, $repetida)->expectsOutput($mensaje)->assertFailed();

    expect(User::query()->where('es_sistema', false)->exists())->toBeFalse()
        ->and(Party::query()->exists())->toBeFalse();
})->with([
    'claves distintas' => [[], 'Una-clave-larga-1', 'Otra-clave-larga-1', 'Las claves no coinciden.'],
    'clave corta' => [[], 'corta', null, 'La clave necesita al menos 12 caracteres.'],
    'clave larga pero sin la regla' => [[], 'una-clave-larga-de-prueba', null, 'La contraseña debe tener al menos una mayúscula y una minúscula. La contraseña debe tener al menos un número.'],
    'correo inválido' => [['--email' => 'no-es-correo'], 'Una-clave-larga-1', null, 'El correo no es válido.'],
]);
