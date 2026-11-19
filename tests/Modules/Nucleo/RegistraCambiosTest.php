<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\EventoAuditoria;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ContextoAuditoria;
use App\Modules\Nucleo\Services\HistorialCambiosService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Modules\Nucleo\Fixtures\ModeloAuditado;
use Tests\Modules\Nucleo\Fixtures\ModeloSensible;

beforeEach(fn () => ModeloAuditado::crearTabla());

function historial(ModeloAuditado $fila): array
{
    return app(HistorialCambiosService::class)->de($fila)->all();
}

test('crear deja su fila con los valores nuevos y el autor', function () {
    $ana = User::factory()->create();
    $this->actingAs($ana);

    $fila = ModeloSensible::create(['nombre' => 'Cable', 'monto' => '1248.00']);

    [$cambio] = historial($fila);
    expect($cambio->evento)->toBe(EventoAuditoria::Creado)
        ->and($cambio->user_id)->toBe($ana->id)
        ->and($cambio->valores_anteriores)->toBeNull()
        ->and($cambio->valores_nuevos)->toMatchArray(['nombre' => 'Cable', 'monto' => '1248.00', 'id' => $fila->id])
        ->and($cambio->valores_nuevos)->not->toHaveKeys(['created_by', 'created_at']);
});

test('actualizar guarda solo los campos que cambiaron, con su valor anterior', function () {
    $fila = ModeloSensible::create(['nombre' => 'Cable', 'monto' => '1248.00']);

    $fila->update(['monto' => '1300.50']);

    [$cambio] = historial($fila);
    expect($cambio->evento)->toBe(EventoAuditoria::Actualizado)
        ->and($cambio->valores_anteriores)->toBe(['monto' => '1248.00'])
        ->and($cambio->valores_nuevos)->toBe(['monto' => '1300.50']);
});

test('eliminar guarda cómo estaba la fila', function () {
    $fila = ModeloSensible::create(['nombre' => 'Cable']);

    $fila->delete();

    [$cambio] = historial($fila);
    expect($cambio->evento)->toBe(EventoAuditoria::Eliminado)
        ->and($cambio->valores_anteriores)->toMatchArray(['nombre' => 'Cable'])
        ->and($cambio->valores_nuevos)->toBeNull();
});

test('los campos ocultos y los excluidos no entran a la bitácora', function () {
    $fila = ModeloSensible::create(['nombre' => 'Cable', 'secreto' => 'x', 'nota_interna' => 'y']);
    $fila->update(['secreto' => 'z', 'nota_interna' => 'w']);

    $cambios = historial($fila);
    expect($cambios)->toHaveCount(1)
        ->and($cambios[0]->valores_nuevos)->not->toHaveKeys(['secreto', 'nota_interna']);
});

test('si la transacción se revierte, la bitácora también', function () {
    try {
        DB::transaction(function (): void {
            ModeloSensible::create(['nombre' => 'Cable']);
            throw new RuntimeException('falla');
        });
    } catch (RuntimeException) {
    }

    expect(AuditoriaCambio::count())->toBe(0);
});

test('una tabla no sensible no deja bitácora', function () {
    ModeloAuditado::create(['nombre' => 'Cable'])->update(['nombre' => 'Cable UTP']);

    expect(AuditoriaCambio::count())->toBe(0);
});

test('en web guarda ip y navegador', function () {
    $this->actingAs(User::factory()->create());
    $peticion = Request::create('/', server: ['REMOTE_ADDR' => '10.0.0.7', 'HTTP_USER_AGENT' => 'Navegador de prueba']);
    app()->instance(ContextoAuditoria::class, new ContextoAuditoria(app('auth'), app(UsuarioSistema::class), false, fn (): ?Request => $peticion));

    [$cambio] = historial(ModeloSensible::create(['nombre' => 'Cable']));

    expect($cambio->ip)->toBe('10.0.0.7')->and($cambio->agente)->toBe('Navegador de prueba');
});

test('la bitácora no se modifica ni se borra', function () {
    [$cambio] = historial(ModeloSensible::create(['nombre' => 'Cable']));

    expect(fn () => $cambio->update(['ip' => '1.1.1.1']))->toThrow(LogicException::class)
        ->and(fn () => $cambio->delete())->toThrow(LogicException::class);
});

test('la base rechaza un evento desconocido', function () {
    expect(fn () => DB::table('auditoria_cambios')->insert([
        'auditable_type' => 'x', 'auditable_id' => 1, 'evento' => 'borrado',
        'user_id' => app(UsuarioSistema::class)->id(), 'fecha' => now(),
    ]))->toThrow(QueryException::class);
});

test('sin transacción abierta por quien llama, el cambio y su bitácora entran juntos o no entra ninguno', function () {
    ModeloSensible::created(fn () => throw new RuntimeException('falla después de escribir la bitácora'));

    expect(fn () => ModeloSensible::create(['nombre' => 'Cable']))->toThrow(RuntimeException::class)
        ->and(ModeloAuditado::count())->toBe(0)
        ->and(AuditoriaCambio::count())->toBe(0);
});

test('borrar también es atómico', function () {
    $fila = ModeloSensible::create(['nombre' => 'Cable']);
    ModeloSensible::deleted(fn () => throw new RuntimeException('falla'));

    expect(fn () => $fila->delete())->toThrow(RuntimeException::class)
        ->and(ModeloAuditado::count())->toBe(1)
        ->and(AuditoriaCambio::query()->where('evento', 'eliminado')->count())->toBe(0);
});
