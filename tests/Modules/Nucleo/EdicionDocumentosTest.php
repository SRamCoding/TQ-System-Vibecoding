<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use App\Modules\Nucleo\Excepciones\EdicionRechazada;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\ContextoEfectos;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\TransicionesService;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;
use Tests\Modules\Nucleo\Fixtures\LineaDocumentoPrueba;
use Tests\Modules\Nucleo\Fixtures\MontoHastaMil;
use Tests\Modules\Nucleo\Fixtures\RecibirTodo;
use Tests\Modules\Nucleo\Fixtures\RenombrarDocumentoPrueba;
use Tests\Modules\Nucleo\Fixtures\ServicioDocumentoPrueba;

/*
 * Un documento en un estado no editable no se modifica, venga de donde venga, y sus campos derivados solo los cambia
 * un efecto (plan 1.5, D5 y D8). Todo con el documento de prueba, sin pantalla.
 */

beforeEach(function () {
    test()->seed(ParametrosSeeder::class);
    DocumentoPrueba::crearTabla();
    app(RegistroDocumentos::class)->efecto('nucleo.prueba.recibir', RecibirTodo::class);
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba(transiciones: [
        new TransicionDefinida('enviar', 'Enviar', 'borrador', 'enviado'),
        new TransicionDefinida('recibir', 'Recibir', 'enviado', 'enviado', esDelSistema: true, efectos: ['nucleo.prueba.recibir']),
        new TransicionDefinida('cerrar', 'Cerrar', 'enviado', 'aprobado', condiciones: [MontoHastaMil::class]),
        new TransicionDefinida('anular', 'Anular', ['borrador', 'enviado'], 'anulado', requiereMotivo: true),
    ]));
    app(SincronizadorEstados::class)->sincronizar();
    app(RegistroAcciones::class)->registrar(RenombrarDocumentoPrueba::class);
});

function editorConPermisos(string ...$permisos): User
{
    foreach ($permisos as $permiso) {
        Permission::findOrCreate($permiso);
    }

    return User::factory()->create()->givePermissionTo($permisos);
}

/**
 * @param  array<string, mixed>  $datos
 */
function documentoConLinea(array $datos = []): DocumentoPrueba
{
    $documento = app(TransicionesService::class)->crear(new DocumentoPrueba(['nombre' => 'Pedido de cables', ...$datos]), User::factory()->create())->refresh();
    app(ServicioDocumentoPrueba::class)->agregarLinea($documento->id, 'Cable UTP', '10.00');

    return $documento;
}

function documentoEnviado(array $datos = []): DocumentoPrueba
{
    $documento = documentoConLinea($datos);
    app(TransicionesService::class)->transicionar($documento, 'enviar', $documento->version, editorConPermisos('nucleo.prueba.enviar'));

    return $documento->refresh();
}

function primeraLinea(DocumentoPrueba $documento): LineaDocumentoPrueba
{
    return LineaDocumentoPrueba::query()->where('documento_prueba_id', $documento->id)->orderBy('id')->firstOrFail();
}

// --- Estado editable ---

test('en un estado editable se cambian cabecera y líneas, se agregan y se borran', function () {
    $documento = documentoConLinea();
    $linea = primeraLinea($documento);

    $documento->update(['nombre' => 'Pedido de cables Cat 6']);
    $linea->update(['cantidad' => '12.00']);
    app(ServicioDocumentoPrueba::class)->agregarLinea($documento->id, 'Conector RJ45', '20.00');
    $linea->delete();

    expect($documento->fresh()?->nombre)->toBe('Pedido de cables Cat 6')
        ->and($documento->lineas()->pluck('descripcion')->all())->toBe(['Conector RJ45'])
        ->and($documento->esEditable())->toBeTrue();
});

// --- Estado no editable ---

test('en un estado no editable la cabecera no cambia por modelo, servicio ni acción', function () {
    $documento = documentoEnviado();
    $persona = editorConPermisos('probar-acciones');
    $mensaje = 'El documento de prueba está en «Enviado» y no se puede modificar.';

    expect(fn () => $documento->update(['nombre' => 'Otro']))->toThrow(EdicionRechazada::class, $mensaje)
        ->and(fn () => app(ServicioDocumentoPrueba::class)->renombrar($documento->id, 'Otro'))->toThrow(EdicionRechazada::class, $mensaje)
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar('pruebas.renombrar_documento', ['documento_id' => $documento->id, 'nombre' => 'Otro'], $persona))
        ->toThrow(EdicionRechazada::class, $mensaje)
        ->and($documento->fresh()?->nombre)->toBe('Pedido de cables')
        ->and($documento->esEditable())->toBeFalse();
});

test('en un estado no editable las líneas no se agregan, no cambian ni se borran', function () {
    $documento = documentoEnviado();
    $linea = primeraLinea($documento);

    expect(fn () => app(ServicioDocumentoPrueba::class)->agregarLinea($documento->id, 'Conector RJ45', '20.00'))->toThrow(EdicionRechazada::class, 'está en «Enviado»')
        ->and(fn () => $linea->update(['cantidad' => '12.00']))->toThrow(EdicionRechazada::class, 'está en «Enviado»')
        ->and(fn () => $linea->delete())->toThrow(EdicionRechazada::class, 'está en «Enviado»')
        ->and($documento->lineas()->count())->toBe(1)
        ->and(primeraLinea($documento)->cantidad)->toBe('10.00');
});

test('la cabecera no se borra nunca, ni en un estado editable: se anula', function () {
    $borrador = documentoConLinea();
    $enviado = documentoEnviado();

    expect(fn () => $borrador->delete())->toThrow(EdicionRechazada::class, 'El documento de prueba no se borra: si ya no sirve, anúlalo.')
        ->and(fn () => $enviado->delete())->toThrow(EdicionRechazada::class, 'no se borra')
        ->and(DocumentoPrueba::query()->count())->toBe(2);
});

test('una instancia leída antes del cambio de estado no se salta el bloqueo', function () {
    $documento = documentoConLinea();
    $vieja = DocumentoPrueba::query()->findOrFail($documento->id);
    app(TransicionesService::class)->transicionar($documento, 'enviar', $documento->version, editorConPermisos('nucleo.prueba.enviar'));

    $vieja->nombre = 'Otro';

    expect(fn () => $vieja->save())->toThrow(EdicionRechazada::class, 'está en «Enviado»');
});

test('las transiciones siguen cambiando el estado de un documento no editable', function () {
    $documento = documentoEnviado();
    $familia = TipoMotivo::query()->where('codigo', 'nucleo.prueba.anular')->firstOrFail();
    $motivo = Motivo::query()->create(['tipo_motivo_id' => $familia->id, 'codigo' => 'error', 'nombre' => 'Error']);

    app(TransicionesService::class)->transicionar($documento, 'anular', $documento->version, editorConPermisos('nucleo.prueba.anular'), new MotivoElegido($motivo->id));

    expect($documento->fresh()?->esEditable())->toBeFalse()
        ->and($documento->fresh()?->version)->toBe(3);
});

test('el estado solo lo pone el servicio de transiciones, al crear y al cambiarlo', function () {
    $documento = documentoConLinea();
    $enviado = Estado::query()->where('documento_type', 'documento_prueba')->where('codigo', 'enviado')->value('id');
    $documento->estado_id = $enviado;
    $sinServicio = new DocumentoPrueba(['nombre' => 'Sin historial']);
    $sinServicio->estado_id = $enviado;

    expect(fn () => $documento->save())->toThrow(LogicException::class, 'El estado de un documento (documento_prueba) solo lo pone TransicionesService')
        ->and(fn () => $sinServicio->save())->toThrow(LogicException::class, 'solo lo pone TransicionesService')
        ->and($documento->fresh()?->esEditable())->toBeTrue()
        ->and(DocumentoPrueba::query()->count())->toBe(1);
});

// --- Campos derivados ---

test('un efecto cambia los derivados en un estado no editable', function () {
    $documento = documentoEnviado();

    app(TransicionesService::class)->transicionarComoSistema($documento, 'recibir');

    expect(primeraLinea($documento)->cantidad_recibida)->toBe('10.00')
        ->and($documento->fresh()?->total_recibido)->toBe('10.00');
});

test('fuera de un efecto los derivados no cambian, ni en un estado editable', function () {
    $documento = documentoConLinea();
    $linea = primeraLinea($documento);
    $documento->total_recibido = '5.00';

    expect(fn () => $linea->update(['cantidad_recibida' => '5.00']))->toThrow(EdicionRechazada::class, '«cantidad_recibida» lo actualizan los documentos que dependen de este')
        ->and(fn () => $documento->save())->toThrow(EdicionRechazada::class, '«total_recibido»')
        ->and(primeraLinea($documento)->cantidad_recibida)->toBe('0.00');
});

test('dentro de un efecto, lo que no es derivado sigue bloqueado en un estado no editable', function () {
    $documento = documentoEnviado();
    $linea = primeraLinea($documento);

    expect(fn () => app(ContextoEfectos::class)->durante(new RecibirTodo, fn () => $linea->update(['cantidad' => '12.00'])))
        ->toThrow(EdicionRechazada::class, 'está en «Enviado»');
});

test('tras un efecto que falla, el contexto queda cerrado y el derivado vuelve a bloquearse', function () {
    $documento = documentoConLinea();
    $linea = primeraLinea($documento);

    expect(fn () => app(ContextoEfectos::class)->durante(new RecibirTodo, fn () => throw new RuntimeException('falla')))->toThrow(RuntimeException::class)
        ->and(fn () => $linea->update(['cantidad_recibida' => '5.00']))->toThrow(EdicionRechazada::class, '«cantidad_recibida»');
});

// --- El mensaje ofrece solo las salidas reales ---

test('el mensaje nombra «Anular» a quien puede anular', function () {
    $documento = documentoEnviado();
    test()->actingAs(editorConPermisos('nucleo.prueba.anular'));

    expect(fn () => $documento->update(['nombre' => 'Otro']))
        ->toThrow(EdicionRechazada::class, 'El documento de prueba está en «Enviado» y no se puede modificar. Lo que puedes hacer: «Anular».');
});

test('sin ningún permiso, el mensaje no nombra salidas', function () {
    $documento = documentoEnviado();
    test()->actingAs(User::factory()->create());

    try {
        $documento->update(['nombre' => 'Otro']);
    } catch (EdicionRechazada $error) {
        expect($error->getMessage())->toBe('El documento de prueba está en «Enviado» y no se puede modificar.');
    }

    expect($error ?? null)->toBeInstanceOf(EdicionRechazada::class);
});

test('el mensaje no nombra una salida cuya condición no se cumple', function () {
    $documento = documentoEnviado(['monto' => '1500.00']);
    test()->actingAs(editorConPermisos('nucleo.prueba.cerrar', 'nucleo.prueba.anular'));
    $abajo = documentoEnviado(['monto' => '800.00']);

    expect(fn () => $documento->update(['nombre' => 'Otro']))->toThrow(EdicionRechazada::class, 'Lo que puedes hacer: «Anular».')
        ->and(fn () => $abajo->update(['nombre' => 'Otro']))->toThrow(EdicionRechazada::class, 'Lo que puedes hacer: «Cerrar» o «Anular».');
});
