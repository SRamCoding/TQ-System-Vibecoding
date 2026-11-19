<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Estados\AprobacionDeTransicion;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\PermisoPorAmbito;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\TransicionesService;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Modules\Nucleo\Fixtures\AprobacionesCompletas;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;

/*
 * El servicio de transiciones es el único camino para crear un documento con estados y cambiarle el estado (plan
 * 1.5, D5-D7 y D9). Todo con el documento de prueba, sin pantalla.
 */

beforeEach(function () {
    test()->seed(ParametrosSeeder::class);
    DocumentoPrueba::crearTabla();
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba);
    app(SincronizadorEstados::class)->sincronizar();
});

function transiciones(): TransicionesService
{
    return app(TransicionesService::class);
}

/**
 * @param  array<string, mixed>  $datos
 */
function documentoEnBorrador(array $datos = []): DocumentoPrueba
{
    return transiciones()->crear(new DocumentoPrueba(['nombre' => 'Pedido de cables', ...$datos]), User::factory()->create());
}

function personaQuePuede(string ...$permisos): User
{
    foreach ($permisos as $permiso) {
        Permission::findOrCreate($permiso);
    }

    return User::factory()->create()->givePermissionTo($permisos);
}

function codigoDeEstado(DocumentoPrueba $documento): string
{
    return Estado::query()->findOrFail($documento->fresh()?->estado_id)->codigo;
}

function motivoDeAnular(string $codigo): Motivo
{
    $familia = TipoMotivo::query()->where('codigo', 'nucleo.prueba.anular')->firstOrFail();

    return Motivo::query()->firstOrCreate(['tipo_motivo_id' => $familia->id, 'codigo' => $codigo], ['nombre' => ucfirst($codigo)]);
}

function aprobacionesCompletas(): void
{
    app()->bind(AprobacionDeTransicion::class, AprobacionesCompletas::class);
}

// --- Creación ---

test('la creación deja el estado inicial y su primera fila del historial', function () {
    $documento = documentoEnBorrador();
    $fila = HistorialEstado::query()->where('documentable_id', $documento->id)->sole();

    expect(codigoDeEstado($documento))->toBe('borrador')
        ->and($fila->documentable_type)->toBe('documento_prueba')
        ->and($fila->desde_estado_id)->toBeNull()
        ->and($fila->transicion_id)->toBeNull()
        ->and($fila->hacia_estado_id)->toBe($documento->estado_id)
        ->and($fila->user_id)->toBe($documento->created_by);
});

test('el sistema crea un documento y guarda a la persona que lo originó', function () {
    $sistema = User::query()->findOrFail(app(UsuarioSistema::class)->id());
    $persona = User::factory()->create();

    $documento = transiciones()->crear(new DocumentoPrueba(['nombre' => 'Generado']), $sistema, $persona);
    $fila = HistorialEstado::query()->where('documentable_id', $documento->id)->sole();

    expect($fila->user_id)->toBe($sistema->id)
        ->and($fila->originado_por_user_id)->toBe($persona->id);
});

test('crear no acepta un documento ya guardado: cambiaría su estado sin transición', function () {
    $documento = documentoEnBorrador();

    expect(fn () => transiciones()->crear($documento, User::factory()->create()))->toThrow(LogicException::class, 'crear() recibe un documento sin guardar')
        ->and(HistorialEstado::query()->where('documentable_id', $documento->id)->count())->toBe(1);
});

// --- Transicionar ---

test('una persona con el permiso cambia el estado: sube la versión y queda en el historial', function () {
    $documento = documentoEnBorrador();
    $persona = personaQuePuede('nucleo.prueba.enviar');

    $fila = transiciones()->transicionar($documento, 'enviar', 1, $persona, comentario: '  Sale hoy  ');

    expect(codigoDeEstado($documento))->toBe('enviado')
        ->and($documento->version)->toBe(2)
        ->and($documento->updated_by)->toBe($persona->id)
        ->and($fila->user_id)->toBe($persona->id)
        ->and($fila->desde?->codigo)->toBe('borrador')
        ->and($fila->comentario)->toBe('Sale hoy')
        ->and($fila->motivo_id)->toBeNull();
});

test('dos pedidos con la misma versión: el segundo falla con VersionDesactualizada', function () {
    $documento = documentoEnBorrador();
    $otraVista = DocumentoPrueba::query()->findOrFail($documento->id);
    $persona = personaQuePuede('nucleo.prueba.enviar', 'nucleo.prueba.anular');

    transiciones()->transicionar($documento, 'enviar', 1, $persona);

    expect(fn () => transiciones()->transicionar($otraVista, 'anular', 1, $persona, new MotivoElegido(motivoDeAnular('precio')->id)))
        ->toThrow(VersionDesactualizada::class, 'Otra persona cambió el documento de prueba')
        ->and(codigoDeEstado($documento))->toBe('enviado');
});

test('una transición que no sale del estado actual falla', function () {
    $documento = documentoEnBorrador();
    $persona = personaQuePuede('nucleo.prueba.enviar');
    transiciones()->transicionar($documento, 'enviar', 1, $persona);

    expect(fn () => transiciones()->transicionar($documento, 'enviar', 2, $persona))
        ->toThrow(TransicionRechazada::class, 'No se puede hacer «Enviar»: el documento de prueba está en «Enviado».');
});

test('sin permiso falla y no escribe nada', function () {
    $documento = documentoEnBorrador();

    expect(fn () => transiciones()->transicionar($documento, 'enviar', 1, User::factory()->create()))
        ->toThrow(TransicionRechazada::class, 'No tienes permiso para «Enviar»')
        ->and(codigoDeEstado($documento))->toBe('borrador')
        ->and(HistorialEstado::query()->count())->toBe(1);
});

test('el permiso en el segundo ámbito del documento basta; en uno ajeno, no', function () {
    [$primera, $segunda, $ajena] = UnidadOrganizativa::factory()->count(3)->create()->all();
    Permission::findOrCreate('nucleo.prueba.enviar');
    $rol = Role::findOrCreate('envia-pruebas');
    $rol->givePermissionTo('nucleo.prueba.enviar');
    $enSegunda = User::factory()->create();
    $enAjena = User::factory()->create();
    PermisoPorAmbito::factory()->create(['user_id' => $enSegunda->id, 'rol_id' => $rol->id, 'ambito_id' => $segunda->id]);
    PermisoPorAmbito::factory()->create(['user_id' => $enAjena->id, 'rol_id' => $rol->id, 'ambito_id' => $ajena->id]);
    $documento = documentoEnBorrador(['unidad_id' => $primera->id, 'otra_unidad_id' => $segunda->id]);

    expect(fn () => transiciones()->transicionar($documento, 'enviar', 1, $enAjena))->toThrow(TransicionRechazada::class, 'No tienes permiso');

    transiciones()->transicionar($documento, 'enviar', 1, $enSegunda);

    expect(codigoDeEstado($documento))->toBe('enviado');
});

test('la condición de ejemplo pasa con 1,000.00 y falla encima sin escribir nada', function () {
    $persona = personaQuePuede('nucleo.prueba.enviar');
    $alto = documentoEnBorrador(['monto' => '1000.01']);
    $justo = documentoEnBorrador(['monto' => '1000.00']);

    expect(fn () => transiciones()->transicionar($alto, 'enviar', 1, $persona))
        ->toThrow(TransicionRechazada::class, '«Enviar» solo se hace con montos de hasta 1,000.00.')
        ->and(codigoDeEstado($alto))->toBe('borrador')
        ->and($alto->fresh()?->version)->toBe(1)
        ->and(HistorialEstado::query()->where('documentable_id', $alto->id)->count())->toBe(1);

    transiciones()->transicionar($justo, 'enviar', 1, $persona);

    expect(codigoDeEstado($justo))->toBe('enviado');
});

// --- Sistema y aprobaciones ---

test('una persona no ejecuta una transición con aprobación ni una del sistema, aunque tenga el permiso', function () {
    $persona = personaQuePuede('nucleo.prueba.pedir_aprobacion', 'nucleo.prueba.aprobar');
    $documento = documentoEnBorrador();
    transiciones()->transicionar($documento, 'pedir_aprobacion', 1, $persona);

    expect(fn () => transiciones()->transicionar($documento, 'aprobar', 2, $persona))
        ->toThrow(TransicionRechazada::class, '«Aprobar» se completa con las aprobaciones, no directamente.');

    aprobacionesCompletas();
    transiciones()->transicionarComoSistema($documento, 'aprobar', $persona);

    expect(fn () => transiciones()->transicionar($documento, 'cerrar_por_sistema', 3, $persona))
        ->toThrow(TransicionRechazada::class, '«Cerrar» lo hace el sistema, no una persona.');
});

test('sin reglas de aprobación que calcen, el sistema tampoco aprueba', function () {
    $documento = documentoEnBorrador();
    transiciones()->transicionar($documento, 'pedir_aprobacion', 1, personaQuePuede('nucleo.prueba.pedir_aprobacion'));

    expect(fn () => transiciones()->transicionarComoSistema($documento, 'aprobar'))
        ->toThrow(TransicionRechazada::class, 'No hay reglas de aprobación para el documento de prueba');
});

test('con las aprobaciones completas, el sistema aprueba y guarda a quien dio la última firma', function () {
    aprobacionesCompletas();
    $quienFirma = User::factory()->create();
    $documento = documentoEnBorrador();
    transiciones()->transicionar($documento, 'pedir_aprobacion', 1, personaQuePuede('nucleo.prueba.pedir_aprobacion'));

    $fila = transiciones()->transicionarComoSistema($documento, 'aprobar', $quienFirma);

    expect(codigoDeEstado($documento))->toBe('aprobado')
        ->and($fila->user_id)->toBe(app(UsuarioSistema::class)->id())
        ->and($fila->originado_por_user_id)->toBe($quienFirma->id);

    $cierre = transiciones()->transicionarComoSistema($documento, 'cerrar_por_sistema');

    expect($cierre->originado_por_user_id)->toBeNull()
        ->and(codigoDeEstado($documento))->toBe('enviado');
});

test('el sistema no ejecuta una transición de persona', function () {
    expect(fn () => transiciones()->transicionarComoSistema(documentoEnBorrador(), 'enviar'))
        ->toThrow(TransicionRechazada::class, '«Enviar» la hace una persona');
});

// --- Motivos ---

test('anular exige un motivo activo de su lista', function () {
    $persona = personaQuePuede('nucleo.prueba.anular');
    $documento = documentoEnBorrador();
    $desactivado = motivoDeAnular('plazo');
    $desactivado->update(['es_activo' => false]);
    $ajeno = Motivo::query()->create(['tipo_motivo_id' => TipoMotivo::query()->create(['codigo' => 'otra.lista', 'nombre' => 'Otra'])->id, 'codigo' => 'precio', 'nombre' => 'Precio']);

    expect(fn () => transiciones()->transicionar($documento, 'anular', 1, $persona))->toThrow(TransicionRechazada::class, 'Elige el motivo para «Anular».')
        ->and(fn () => transiciones()->transicionar($documento, 'anular', 1, $persona, new MotivoElegido($desactivado->id)))->toThrow(TransicionRechazada::class, 'ya no se usa')
        ->and(fn () => transiciones()->transicionar($documento, 'anular', 1, $persona, new MotivoElegido($ajeno->id)))->toThrow(TransicionRechazada::class, 'no está entre los de «Anular»')
        ->and(fn () => transiciones()->transicionar($documento, 'anular', 1, $persona, new MotivoElegido(motivoDeAnular('precio')->id, 'Detalle de más')))->toThrow(TransicionRechazada::class, 'solo se escribe cuando el motivo es «Otro»');

    $fila = transiciones()->transicionar($documento, 'anular', 1, $persona, new MotivoElegido(motivoDeAnular('precio')->id), 'Lo pidió el cliente');

    expect(codigoDeEstado($documento))->toBe('anulado')
        ->and($fila->motivo?->codigo)->toBe('precio')
        ->and($fila->motivo_detalle)->toBeNull()
        ->and($fila->comentario)->toBe('Lo pidió el cliente');
});

test('«Otro» con menos del mínimo de caracteres falla; con el mínimo, guarda el detalle', function () {
    $persona = personaQuePuede('nucleo.prueba.anular');
    $documento = documentoEnBorrador();
    $otro = motivoDeAnular('otro');

    expect(fn () => transiciones()->transicionar($documento, 'anular', 1, $persona, new MotivoElegido($otro->id, '  123456789  ')))
        ->toThrow(TransicionRechazada::class, 'Con «Otro», describe el motivo en al menos 10 caracteres.');

    $fila = transiciones()->transicionar($documento, 'anular', 1, $persona, new MotivoElegido($otro->id, ' 1234567890 '));

    expect($fila->motivo_es_otro)->toBeTrue()
        ->and($fila->motivo_detalle)->toBe('1234567890');
});

test('una transición sin motivo no lo acepta', function () {
    $motivo = new MotivoElegido(motivoDeAnular('precio')->id);

    expect(fn () => transiciones()->transicionar(documentoEnBorrador(), 'enviar', 1, personaQuePuede('nucleo.prueba.enviar'), $motivo))
        ->toThrow(TransicionRechazada::class, '«Enviar» no lleva motivo.');
});
