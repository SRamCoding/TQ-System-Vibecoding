<?php

declare(strict_types=1);

use App\Modules\Nucleo\Excepciones\AutorDesconocido;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ContextoAuditoria;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

test('el usuario de sistema y los inactivos no entran al panel', function () {
    $panel = Filament::getPanel('app');

    expect(User::query()->where('es_sistema', true)->sole()->canAccessPanel($panel))->toBeFalse()
        ->and(User::factory()->inactivo()->create()->canAccessPanel($panel))->toBeFalse()
        ->and(User::factory()->create()->canAccessPanel($panel))->toBeTrue();
});

test('el usuario de sistema es su propio autor y los demás tienen autor', function () {
    $sistema = User::query()->where('es_sistema', true)->sole();

    expect($sistema->created_by)->toBe($sistema->id)
        ->and(User::factory()->create()->created_by)->toBe($sistema->id);
});

test('la base rechaza un usuario sin created_by', function () {
    expect(fn () => DB::table('users')->insert(['name' => 'X', 'email' => 'x@techquk.test', 'password' => 'x']))
        ->toThrow(QueryException::class);
});

test('sin sesión, quien cambia su propia fila es el mismo usuario (recordarme, cambio de clave)', function () {
    $usuario = User::factory()->create();
    app()->instance(ContextoAuditoria::class, new ContextoAuditoria(app('auth'), app(UsuarioSistema::class), false, fn (): ?Request => null));

    $usuario->setRememberToken('nuevo-token');
    $usuario->save();

    expect($usuario->refresh()->updated_by)->toBe($usuario->id);
});

test('sin sesión no se crea un usuario', function () {
    app()->instance(ContextoAuditoria::class, new ContextoAuditoria(app('auth'), app(UsuarioSistema::class), false, fn (): ?Request => null));

    expect(fn () => User::factory()->create())->toThrow(AutorDesconocido::class);
});
