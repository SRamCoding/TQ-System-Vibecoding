<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\RegistrarMiTelefono;
use App\Modules\Nucleo\Excepciones\PartyRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use Illuminate\Validation\ValidationException;

/*
 * Plan 1.4h, D2: cada persona cambia el teléfono de su propia ficha, con la versión leída al abrir.
 */

function registrarMiTelefono(User $usuario, ?string $telefono, int $version): mixed
{
    return app(EjecutorAcciones::class)->ejecutar('nucleo.registrar-mi-telefono', ['telefono' => $telefono, 'version' => $version], $usuario);
}

test('el núcleo la registra sin permiso: es su propio dato', function () {
    $accion = app(RegistroAcciones::class)->obtener('nucleo.registrar-mi-telefono');

    expect($accion)->toBeInstanceOf(RegistrarMiTelefono::class)
        ->and($accion->permiso())->toBeNull();
});

test('cambia el teléfono de su ficha, y solo ese campo, con registro en la bitácora de la ficha', function () {
    $usuario = User::factory()->create();
    $ficha = Party::query()->findOrFail($usuario->party_id);
    $antes = $ficha->only(['razon_social', 'email', 'direccion_fiscal']);

    registrarMiTelefono($usuario, '987 654 321', $ficha->version);

    $ficha->refresh();
    expect($ficha->telefono)->toBe('987 654 321')
        ->and($ficha->only(['razon_social', 'email', 'direccion_fiscal']))->toBe($antes)
        ->and(AuditoriaCambio::query()->where('auditable_id', $ficha->id)->get()
            ->contains(fn (AuditoriaCambio $c): bool => ($c->valores_nuevos['telefono'] ?? null) === '987 654 321'))->toBeTrue();
});

test('nunca cambia la ficha de otra persona: siempre la de quien lo ejecuta', function () {
    $una = User::factory()->create();
    $otra = User::factory()->create();
    $fichaOtra = Party::query()->findOrFail($otra->party_id);

    registrarMiTelefono($una, '111 222 333', Party::query()->findOrFail($una->party_id)->version);

    expect($fichaOtra->refresh()->telefono)->not->toBe('111 222 333')
        ->and(array_map(fn ($campo): string => $campo->nombre, app(RegistroAcciones::class)->obtener('nucleo.registrar-mi-telefono')->campos()))->toBe(['telefono', 'version']);
});

test('vacío lo borra; con la versión vieja, el aviso de siempre', function () {
    $usuario = User::factory()->create();
    $ficha = Party::query()->findOrFail($usuario->party_id);
    registrarMiTelefono($usuario, '987 654 321', $ficha->version);

    expect(fn () => registrarMiTelefono($usuario, '000', $ficha->version))->toThrow(VersionDesactualizada::class);

    registrarMiTelefono($usuario, null, $ficha->refresh()->version);
    expect($ficha->refresh()->telefono)->toBeNull();
});

test('más de 30 caracteres se rechaza, como en la ficha', function () {
    $usuario = User::factory()->create();

    expect(fn () => registrarMiTelefono($usuario, str_repeat('9', 31), Party::query()->findOrFail($usuario->party_id)->version))
        ->toThrow(ValidationException::class);
});

test('una cuenta sin ficha no tiene teléfono que registrar', function () {
    $usuario = User::factory()->create();
    $usuario->forceFill(['party_id' => null]);

    expect(fn () => app(RegistrarMiTelefono::class)->ejecutar(['telefono' => '1', 'version' => 1], $usuario))->toThrow(PartyRechazada::class);
});
