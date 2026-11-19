<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\DefinicionDocumentoInvalida;
use App\Modules\Nucleo\Excepciones\MotivoRechazado;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\PermisoPorAmbito;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\TransicionesService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;
use Tests\Modules\Nucleo\Fixtures\RegistrarAdendaPrueba;

/*
 * Las transiciones de persona se ejecutan como acciones registradas, sin la interfaz (regla 7; plan 1.5, D9). La
 * acción solo traduce campos: el servicio revisa permiso con ámbito, motivo y versión. También las acciones que
 * editan la presentación de los estados y las listas de motivos.
 */

beforeEach(function () {
    test()->seed(ParametrosSeeder::class);
    DocumentoPrueba::crearTabla();
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba(transiciones: [
        ...DefinicionPrueba::transicionesBase(),
        new TransicionDefinida('registrar_adenda', 'Registrar adenda', 'enviado', 'enviado', accionPropia: true),
    ]));
    app(SincronizadorEstados::class)->sincronizar();
    app(RegistroAcciones::class)->registrar(RegistrarAdendaPrueba::class);
    test()->seed(PermisosSeeder::class);
});

function aePersona(string ...$permisos): User
{
    return User::factory()->create()->givePermissionTo($permisos);
}

function aeEjecutar(string $clave, array $datos, User $persona): mixed
{
    return app(EjecutorAcciones::class)->ejecutar($clave, $datos, $persona);
}

function aeDocumento(array $datos = []): DocumentoPrueba
{
    return app(TransicionesService::class)->crear(new DocumentoPrueba(['nombre' => 'Pedido de cables', ...$datos]), User::factory()->create())->refresh();
}

function aeEstado(DocumentoPrueba $documento): string
{
    return Estado::query()->findOrFail($documento->fresh()?->estado_id)->codigo;
}

function aeMotivo(string $nombre): Motivo
{
    $familia = TipoMotivo::query()->where('codigo', 'nucleo.prueba.anular')->firstOrFail();

    return Motivo::query()->create(['tipo_motivo_id' => $familia->id, 'codigo' => str($nombre)->slug('_')->value(), 'nombre' => $nombre]);
}

// --- Transiciones como acciones ---

test('nucleo.prueba.enviar por EjecutorAcciones cambia el estado y deja registro', function () {
    $documento = aeDocumento();
    $persona = aePersona('nucleo.prueba.enviar');

    $fila = aeEjecutar('nucleo.prueba.enviar', ['documento_id' => $documento->id, 'version' => 1, 'comentario' => 'Sale hoy'], $persona);

    expect(aeEstado($documento))->toBe('enviado')
        ->and($fila)->toBeInstanceOf(HistorialEstado::class)
        ->and($fila->user_id)->toBe($persona->id)
        ->and($fila->comentario)->toBe('Sale hoy')
        ->and($documento->fresh()?->updated_by)->toBe($persona->id);
});

test('anular por su acción guarda el motivo y el detalle de «Otro»', function () {
    $documento = aeDocumento();
    $otro = Motivo::query()->whereHas('tipoMotivo', fn ($q) => $q->where('codigo', 'nucleo.prueba.anular'))->where('es_otro', true)->sole();

    $fila = aeEjecutar('nucleo.prueba.anular', [
        'documento_id' => $documento->id, 'version' => 1, 'motivo_id' => $otro->id, 'motivo_detalle' => 'El cliente cambió de idea',
    ], aePersona('nucleo.prueba.anular'));

    expect(aeEstado($documento))->toBe('anulado')
        ->and($fila->motivo_id)->toBe($otro->id)
        ->and($fila->motivo_detalle)->toBe('El cliente cambió de idea');
});

test('la acción la revisa el servicio: sin permiso falla y con el permiso en un ámbito del documento pasa', function () {
    $unidad = UnidadOrganizativa::factory()->create();
    $rol = Role::findOrCreate('envia-pruebas');
    $rol->givePermissionTo('nucleo.prueba.enviar');
    $enUnidad = User::factory()->create();
    PermisoPorAmbito::factory()->create(['user_id' => $enUnidad->id, 'rol_id' => $rol->id, 'ambito_id' => $unidad->id]);
    $documento = aeDocumento(['unidad_id' => $unidad->id]);

    expect(fn () => aeEjecutar('nucleo.prueba.enviar', ['documento_id' => $documento->id, 'version' => 1], User::factory()->create()))
        ->toThrow(TransicionRechazada::class, 'No tienes permiso para «Enviar»');

    aeEjecutar('nucleo.prueba.enviar', ['documento_id' => $documento->id, 'version' => 1], $enUnidad);

    expect(aeEstado($documento))->toBe('enviado');
});

test('la acción de una transición sigue rechazando al usuario de sistema y la versión vieja', function () {
    $documento = aeDocumento();
    $sistema = User::query()->findOrFail(app(UsuarioSistema::class)->id());

    expect(fn () => aeEjecutar('nucleo.prueba.enviar', ['documento_id' => $documento->id, 'version' => 1], $sistema))->toThrow(AccionRechazada::class)
        ->and(fn () => aeEjecutar('nucleo.prueba.enviar', ['documento_id' => $documento->id, 'version' => 7], aePersona('nucleo.prueba.enviar')))
        ->toThrow(VersionDesactualizada::class);
});

test('una acción propia sin el permiso de la transición falla, aunque tenga el suyo', function () {
    $documento = aeDocumento();
    aeEjecutar('nucleo.prueba.enviar', ['documento_id' => $documento->id, 'version' => 1], aePersona('nucleo.prueba.enviar'));
    $datos = ['documento_id' => $documento->id, 'version' => 2, 'monto' => '150.00'];

    expect(fn () => aeEjecutar('pruebas.registrar-adenda', $datos, aePersona('pruebas.registrar-adenda')))
        ->toThrow(TransicionRechazada::class, 'No tienes permiso para «Registrar adenda»');

    aeEjecutar('pruebas.registrar-adenda', $datos, aePersona('pruebas.registrar-adenda', 'nucleo.prueba.registrar_adenda'));

    expect($documento->fresh()?->version)->toBe(3);
});

test('toda transición de persona tiene acción y permiso; ninguna del sistema, con aprobación o de acción propia tiene acción', function () {
    $acciones = app(RegistroAcciones::class)->todas();

    expect(array_keys(array_filter($acciones, fn (string $clave): bool => str_starts_with($clave, 'nucleo.prueba.'), ARRAY_FILTER_USE_KEY)))
        ->toEqualCanonicalizing(['nucleo.prueba.enviar', 'nucleo.prueba.pedir_aprobacion', 'nucleo.prueba.anular'])
        ->and(Permission::query()->where('name', 'like', 'nucleo.prueba.%')->pluck('name')->all())
        ->toEqualCanonicalizing(['nucleo.prueba.enviar', 'nucleo.prueba.pedir_aprobacion', 'nucleo.prueba.anular', 'nucleo.prueba.registrar_adenda'])
        ->and(app(RegistroAcciones::class)->obtener('nucleo.prueba.anular')->nombre())->toBe('Anular documento de prueba');
});

test('una acción propia en una transición del sistema o con aprobación no se registra', function () {
    $definicion = new DefinicionPrueba(transiciones: [
        new TransicionDefinida('cerrar', 'Cerrar', 'enviado', 'enviado', esDelSistema: true, accionPropia: true),
        new TransicionDefinida('anular', 'Anular', 'borrador', 'anulado', requiereMotivo: true),
    ]);

    expect(fn () => (new RegistroDocumentos(new RegistroAcciones(app())))->registrar($definicion))
        ->toThrow(DefinicionDocumentoInvalida::class, '«cerrar» no la ejecuta una persona: no lleva acción propia.');
});

// --- Presentación de los estados ---

test('el nombre y el color de un estado se cambian por su acción, con versión', function () {
    $estado = Estado::query()->where('documento_type', 'documento_prueba')->where('codigo', 'enviado')->sole();
    $persona = aePersona('nucleo.estados.editar-presentacion');

    aeEjecutar('nucleo.estados.cambiar-presentacion', ['estado_id' => $estado->id, 'version' => 1, 'nombre' => 'Enviado al cliente', 'color' => 'alerta'], $persona);
    $estado->refresh();

    expect($estado->nombre)->toBe('Enviado al cliente')
        ->and($estado->color)->toBe(TonoEstado::Alerta)
        ->and($estado->version)->toBe(2)
        ->and(fn () => aeEjecutar('nucleo.estados.cambiar-presentacion', ['estado_id' => $estado->id, 'version' => 1, 'nombre' => 'Otro', 'color' => 'neutro'], $persona))
        ->toThrow(VersionDesactualizada::class)
        ->and(fn () => aeEjecutar('nucleo.estados.cambiar-presentacion', ['estado_id' => $estado->id, 'version' => 2, 'nombre' => 'Otro', 'color' => 'neutro'], User::factory()->create()))
        ->toThrow(AccionRechazada::class);
});

// --- Motivos ---

test('un motivo se agrega con su código; uno igual sin contar mayúsculas, tildes ni espacios no', function () {
    $familia = TipoMotivo::query()->where('codigo', 'nucleo.prueba.anular')->sole();
    $persona = aePersona('nucleo.motivos.editar');

    $motivo = aeEjecutar('nucleo.motivos.agregar', ['tipo_motivo_id' => $familia->id, 'nombre' => '  Precio alto '], $persona);

    expect($motivo->codigo)->toBe('precio_alto')
        ->and($motivo->nombre)->toBe('Precio alto')
        ->and(fn () => aeEjecutar('nucleo.motivos.agregar', ['tipo_motivo_id' => $familia->id, 'nombre' => 'precio  ÁLTO'], $persona))
        ->toThrow(MotivoRechazado::class, 'Ya hay un motivo «precio  ÁLTO» en esta lista.')
        ->and(fn () => aeEjecutar('nucleo.motivos.agregar', ['tipo_motivo_id' => $familia->id, 'nombre' => '¿?'], $persona))
        ->toThrow(MotivoRechazado::class, 'necesita letras o números');
});

test('un motivo se renombra sin chocar con otro de su lista', function () {
    $precio = aeMotivo('Precio alto');
    aeMotivo('Plazo largo');
    $persona = aePersona('nucleo.motivos.editar');

    aeEjecutar('nucleo.motivos.cambiar', ['motivo_id' => $precio->id, 'version' => 1, 'nombre' => 'Precio muy alto'], $persona);

    expect($precio->fresh()?->nombre)->toBe('Precio muy alto')
        ->and($precio->fresh()?->codigo)->toBe('precio_alto')
        ->and(fn () => aeEjecutar('nucleo.motivos.cambiar', ['motivo_id' => $precio->id, 'version' => 2, 'nombre' => 'Plazo largo'], $persona))
        ->toThrow(MotivoRechazado::class, 'Ya hay un motivo «Plazo largo»');
});

test('un motivo desactivado no se elige pero el historial lo sigue mostrando; se puede activar', function () {
    $motivo = aeMotivo('Precio alto');
    $persona = aePersona('nucleo.motivos.editar', 'nucleo.prueba.anular');
    $anulado = aeDocumento();
    $fila = aeEjecutar('nucleo.prueba.anular', ['documento_id' => $anulado->id, 'version' => 1, 'motivo_id' => $motivo->id], $persona);

    aeEjecutar('nucleo.motivos.desactivar', ['motivo_id' => $motivo->id, 'version' => 1], $persona);
    $otroDocumento = aeDocumento();

    expect(fn () => aeEjecutar('nucleo.prueba.anular', ['documento_id' => $otroDocumento->id, 'version' => 1, 'motivo_id' => $motivo->id], $persona))
        ->toThrow(TransicionRechazada::class)
        ->and($fila->fresh()?->motivo?->nombre)->toBe('Precio alto')
        ->and(fn () => aeEjecutar('nucleo.motivos.desactivar', ['motivo_id' => $motivo->id, 'version' => 2], $persona))->toThrow(MotivoRechazado::class, 'ya está inactivo');

    aeEjecutar('nucleo.motivos.activar', ['motivo_id' => $motivo->id, 'version' => 2], $persona);

    expect($motivo->fresh()?->es_activo)->toBeTrue();
});

test('«Otro» no se desactiva', function () {
    $otro = Motivo::query()->whereHas('tipoMotivo', fn ($q) => $q->where('codigo', 'nucleo.prueba.anular'))->where('es_otro', true)->sole();

    expect(fn () => aeEjecutar('nucleo.motivos.desactivar', ['motivo_id' => $otro->id, 'version' => $otro->version], aePersona('nucleo.motivos.editar')))
        ->toThrow(MotivoRechazado::class, '«Otro» siempre está disponible');
});
