<?php

declare(strict_types=1);

use App\Modules\Nucleo\Excepciones\AutorDesconocido;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ContextoAuditoria;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Http\Request;

function contextoWeb(?Request $peticion = null): ContextoAuditoria
{
    return new ContextoAuditoria(app('auth'), app(UsuarioSistema::class), false, fn (): ?Request => $peticion);
}

test('en consola y cola, sin sesión, el autor es el usuario de sistema', function () {
    expect(app(ContextoAuditoria::class)->idActor())->toBe(app(UsuarioSistema::class)->id());
});

test('con sesión, el autor es el usuario autenticado', function () {
    $ana = User::factory()->create();

    $this->actingAs($ana);

    expect(app(ContextoAuditoria::class)->idActor())->toBe($ana->id);
});

test('el actor fijado manda sobre la sesión y se restaura al terminar', function () {
    $ana = User::factory()->create();
    $luis = User::factory()->create();
    $contexto = app(ContextoAuditoria::class);
    $this->actingAs($ana);

    $dentro = $contexto->comoUsuario($luis, fn (): int => $contexto->idActor());

    expect($dentro)->toBe($luis->id)
        ->and($contexto->idActor())->toBe($ana->id);
});

test('el actor fijado se restaura aunque la tarea falle', function () {
    $luis = User::factory()->create();
    $contexto = app(ContextoAuditoria::class);

    expect(fn () => $contexto->comoUsuario($luis, fn () => throw new RuntimeException('falla')))
        ->toThrow(RuntimeException::class);
    expect($contexto->idActor())->toBe(app(UsuarioSistema::class)->id());
});

test('en web sin sesión no hay autor: se rechaza, salvo que el modelo proponga uno', function () {
    $contexto = contextoWeb();

    expect(fn () => $contexto->idActor())->toThrow(AutorDesconocido::class)
        ->and($contexto->idActor(porDefecto: 42))->toBe(42);
});

test('en web guarda ip y agente; en consola no', function () {
    $peticion = Request::create('/', server: ['REMOTE_ADDR' => '10.0.0.7', 'HTTP_USER_AGENT' => str_repeat('a', 300)]);

    expect(contextoWeb($peticion)->ip())->toBe('10.0.0.7')
        ->and(contextoWeb($peticion)->agente())->toHaveLength(255)
        ->and(app(ContextoAuditoria::class)->ip())->toBeNull()
        ->and(app(ContextoAuditoria::class)->agente())->toBeNull();
});
