<?php

declare(strict_types=1);

use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Models\EfectoAplicado;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;

/*
 * El historial de estados es bitácora: solo se inserta (plan 1.5, D2). Un efecto aplicado solo se marca ejecutado o
 * revertido, una vez (D3).
 */

function filaDeCreacionPrueba(): HistorialEstado
{
    DocumentoPrueba::crearTabla();
    $registro = new RegistroDocumentos(new RegistroAcciones(app()));
    $registro->registrar(new DefinicionPrueba);
    (new SincronizadorEstados($registro))->sincronizar();
    $borrador = Estado::query()->where('documento_type', 'documento_prueba')->where('codigo', 'borrador')->firstOrFail();

    return HistorialEstado::query()->create([
        'documentable_type' => 'documento_prueba', 'documentable_id' => 1,
        'hacia_estado_id' => $borrador->id, 'user_id' => app(UsuarioSistema::class)->id(),
    ]);
}

test('el historial de estados no se actualiza ni se borra', function () {
    $fila = filaDeCreacionPrueba();

    expect(fn () => $fila->update(['comentario' => 'Corregido']))->toThrow(LogicException::class, 'no se modifica')
        ->and(fn () => $fila->delete())->toThrow(LogicException::class, 'no se borra');
});

test('un efecto aplicado se marca ejecutado y revertido una sola vez, y no se borra', function () {
    $fila = filaDeCreacionPrueba();
    $segunda = HistorialEstado::query()->create([...$fila->only(['documentable_type', 'documentable_id', 'hacia_estado_id', 'user_id'])]);
    $efecto = EfectoAplicado::query()->create(['historial_estado_id' => $fila->id, 'clave' => 'nucleo.prueba.aviso', 'orden' => 1, 'es_externo' => true]);

    $efecto->update(['ejecutado_at' => now()]);
    $efecto->update(['revertido_por_historial_id' => $segunda->id]);

    expect(fn () => $efecto->update(['ejecutado_at' => now()->addMinute()]))->toThrow(LogicException::class, 'una vez')
        ->and(fn () => $efecto->update(['clave' => 'otra']))->toThrow(LogicException::class, 'una vez')
        ->and(fn () => $efecto->delete())->toThrow(LogicException::class, 'no se borra')
        ->and($efecto->fresh()?->revertido_por_historial_id)->toBe($segunda->id);
});
