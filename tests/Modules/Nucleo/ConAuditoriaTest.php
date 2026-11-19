<?php

declare(strict_types=1);

use App\Modules\Nucleo\Excepciones\AutorDesconocido;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ContextoAuditoria;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Modules\Nucleo\Fixtures\ModeloAuditado;

beforeEach(fn () => ModeloAuditado::crearTabla());

test('al crear guarda quién lo creó y deja updated_by vacío', function () {
    $ana = User::factory()->create();
    $this->actingAs($ana);

    $fila = ModeloAuditado::create(['nombre' => 'Cable'])->refresh();

    expect($fila->created_by)->toBe($ana->id)
        ->and($fila->created_at)->not->toBeNull()
        ->and($fila->updated_by)->toBeNull();
});

test('al actualizar guarda quién lo cambió', function () {
    $fila = ModeloAuditado::create(['nombre' => 'Cable']);
    $luis = User::factory()->create();
    $this->actingAs($luis);

    $fila->update(['nombre' => 'Cable UTP']);

    expect($fila->refresh()->updated_by)->toBe($luis->id)
        ->and($fila->created_by)->toBe(app(UsuarioSistema::class)->id());
});

test('en consola el autor es el usuario de sistema', function () {
    expect(ModeloAuditado::create(['nombre' => 'Cable'])->created_by)->toBe(app(UsuarioSistema::class)->id());
});

test('en web sin sesión no se guarda nada', function () {
    app()->instance(ContextoAuditoria::class, new ContextoAuditoria(app('auth'), app(UsuarioSistema::class), false, fn (): ?Request => null));

    expect(fn () => ModeloAuditado::create(['nombre' => 'Cable']))->toThrow(AutorDesconocido::class)
        ->and(ModeloAuditado::count())->toBe(0);
});

test('la base rechaza una fila sin created_by aunque se salte el modelo', function () {
    expect(fn () => DB::table('pruebas_auditadas')->insert(['nombre' => 'Cable']))
        ->toThrow(QueryException::class);
});

test('un autor puesto a mano no se respeta', function () {
    $ana = User::factory()->create();
    $intruso = User::factory()->create();
    $this->actingAs($ana);

    $fila = new ModeloAuditado(['nombre' => 'Cable']);
    $fila->forceFill(['created_by' => $intruso->id])->save();

    expect($fila->refresh()->created_by)->toBe($ana->id);
});
