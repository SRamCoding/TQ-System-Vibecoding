<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\EstadosSeeder;
use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Estados\EstadoDefinido;
use App\Modules\Nucleo\Estados\MotivoInicial;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use App\Modules\Nucleo\Excepciones\SincronizacionEstadosRechazada;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\TransicionEstado;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\ContextoTransicion;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Support\Facades\DB;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;

/*
 * El sincronizador copia las definiciones a la base en cada despliegue (plan 1.5, D1, D2 y D9): se puede repetir,
 * no pisa lo editado por pantalla, no quita lo que se usa y nunca borra.
 */

beforeEach(fn () => DocumentoPrueba::crearTabla());

/**
 * Corre el sincronizador con la definición de prueba, como en un despliegue nuevo con esa versión del código.
 *
 * @param  list<EstadoDefinido>|null  $estados
 * @param  list<TransicionDefinida>|null  $transiciones
 */
function sincronizarPrueba(?array $estados = null, ?array $transiciones = null): void
{
    $registro = new RegistroDocumentos(new RegistroAcciones(app()));
    $registro->registrar(new DefinicionPrueba($estados, $transiciones));
    (new SincronizadorEstados($registro))->sincronizar();
}

function estadoPrueba(string $codigo): Estado
{
    return Estado::query()->where('documento_type', 'documento_prueba')->where('codigo', $codigo)->firstOrFail();
}

function transicionPrueba(string $codigo, string $desde): TransicionEstado
{
    return TransicionEstado::query()->where('codigo', $codigo)->where('desde_estado_id', estadoPrueba($desde)->id)->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function fotoDeEstados(): array
{
    return [
        'estados' => DB::table('estados')->orderBy('id')->get()->map(fn (object $fila): array => (array) $fila)->all(),
        'transiciones' => DB::table('transiciones_estado')->orderBy('id')->get()->map(fn (object $fila): array => (array) $fila)->all(),
        'familias' => DB::table('tipos_motivo')->orderBy('id')->get()->map(fn (object $fila): array => (array) $fila)->all(),
        'motivos' => DB::table('motivos')->orderBy('id')->get()->map(fn (object $fila): array => (array) $fila)->all(),
        'bitacora' => AuditoriaCambio::query()->count(),
    ];
}

test('crea estados, transiciones y la lista de motivos con su «Otro»', function () {
    sincronizarPrueba();

    $anular = transicionPrueba('anular', 'enviado');
    $familia = TipoMotivo::query()->where('codigo', 'nucleo.prueba.anular')->firstOrFail();

    expect(Estado::query()->where('documento_type', 'documento_prueba')->orderBy('orden')->pluck('codigo')->all())->toBe(['borrador', 'por_aprobar', 'aprobado', 'enviado', 'anulado'])
        ->and(estadoPrueba('borrador')->es_inicial)->toBeTrue()
        ->and(estadoPrueba('anulado')->color)->toBe(TonoEstado::Anulado)
        ->and(TransicionEstado::query()->where('codigo', 'anular')->count())->toBe(2)
        ->and($anular->permiso)->toBe('nucleo.prueba.anular')
        ->and($anular->hacia_es_cancelacion)->toBeTrue()
        ->and($anular->tipo_motivo_id)->toBe($familia->id)
        ->and($familia->nombre)->toBe('Anular · documento de prueba')
        ->and(transicionPrueba('cerrar_por_sistema', 'aprobado')->permiso)->toBeNull()
        ->and(transicionPrueba('aprobar', 'por_aprobar')->requiere_aprobacion)->toBeTrue()
        ->and(transicionPrueba('aprobar', 'por_aprobar')->permiso)->toBeNull()
        ->and(transicionPrueba('aprobar', 'por_aprobar')->esDelSistema())->toBeFalse()
        ->and(transicionPrueba('cerrar_por_sistema', 'aprobado')->esDelSistema())->toBeTrue()
        ->and(Motivo::query()->where('tipo_motivo_id', $familia->id)->where('es_otro', true)->value('nombre'))->toBe('Otro')
        ->and(Estado::query()->value('created_by'))->toBe(app(UsuarioSistema::class)->id());
});

test('correrlo dos veces no cambia nada', function () {
    sincronizarPrueba();
    $antes = fotoDeEstados();

    sincronizarPrueba();

    expect(fotoDeEstados())->toBe($antes);
});

test('no pisa el nombre ni el color editados por pantalla, y renombrar conserva código e id', function () {
    sincronizarPrueba();
    $borrador = estadoPrueba('borrador');
    $borrador->update(['nombre' => 'En preparación', 'color' => TonoEstado::EnCurso]);

    sincronizarPrueba();

    $despues = estadoPrueba('borrador');
    expect($despues->id)->toBe($borrador->id)
        ->and($despues->nombre)->toBe('En preparación')
        ->and($despues->color)->toBe(TonoEstado::EnCurso);
});

test('el código de un estado, de una transición o de un motivo no cambia', function () {
    sincronizarPrueba();

    expect(fn () => estadoPrueba('borrador')->update(['codigo' => 'preparacion']))->toThrow(LogicException::class, '«codigo» de estados no cambia')
        ->and(fn () => estadoPrueba('borrador')->update(['documento_type' => 'otro']))->toThrow(LogicException::class, '«documento_type» de estados no cambia')
        ->and(fn () => transicionPrueba('enviar', 'borrador')->update(['codigo' => 'mandar']))->toThrow(LogicException::class, '«codigo» de transiciones_estado no cambia')
        ->and(fn () => Motivo::query()->firstOrFail()->update(['codigo' => 'otra_cosa']))->toThrow(LogicException::class, '«codigo» de motivos no cambia');
});

test('un estado que sale de la definición se desactiva con sus transiciones, y vuelve si regresa', function () {
    sincronizarPrueba();
    $estados = array_values(array_filter(DefinicionPrueba::estadosBase(), fn (EstadoDefinido $e): bool => $e->codigo !== 'enviado'));
    $transiciones = [
        new TransicionDefinida('pedir_aprobacion', 'Pedir aprobación', 'borrador', 'por_aprobar'),
        new TransicionDefinida('aprobar', 'Aprobar', 'por_aprobar', 'aprobado', requiereAprobacion: true),
        new TransicionDefinida('anular', 'Anular', 'borrador', 'anulado', requiereMotivo: true),
    ];

    sincronizarPrueba($estados, $transiciones);

    expect(estadoPrueba('enviado')->es_activo)->toBeFalse()
        ->and(transicionPrueba('enviar', 'borrador')->es_activa)->toBeFalse()
        ->and(transicionPrueba('anular', 'enviado')->es_activa)->toBeFalse()
        ->and(transicionPrueba('anular', 'borrador')->es_activa)->toBeTrue();

    sincronizarPrueba();

    expect(estadoPrueba('enviado')->es_activo)->toBeTrue()
        ->and(transicionPrueba('enviar', 'borrador')->es_activa)->toBeTrue();
});

test('quitar un estado con documentos falla sin cambiar nada', function () {
    sincronizarPrueba();
    // Documentos armados a mano en «Enviado», sin pasar por las transiciones.
    foreach (['Uno', 'Dos', 'Tres'] as $nombre) {
        app(ContextoTransicion::class)->durante(fn () => DocumentoPrueba::query()->forceCreate(['estado_id' => estadoPrueba('enviado')->id, 'nombre' => $nombre]));
    }
    $antes = fotoDeEstados();
    $estados = array_values(array_filter(DefinicionPrueba::estadosBase(), fn (EstadoDefinido $e): bool => $e->codigo !== 'enviado'));
    $estados[] = new EstadoDefinido('nuevo', 'Nuevo', TonoEstado::Neutro);

    expect(fn () => sincronizarPrueba($estados, []))->toThrow(SincronizacionEstadosRechazada::class, 'No se puede quitar el estado «Enviado»: 3 documentos de tipo documento de prueba siguen en él.')
        ->and(fotoDeEstados())->toBe($antes);
});

test('el estado inicial puede pasar a otro', function () {
    sincronizarPrueba();
    $estados = DefinicionPrueba::estadosBase();
    $estados[0] = new EstadoDefinido('borrador', 'Borrador', TonoEstado::Neutro, esEditable: true);
    $estados[1] = new EstadoDefinido('por_aprobar', 'Por aprobar', TonoEstado::PorAprobar, esInicial: true);

    sincronizarPrueba($estados);

    expect(estadoPrueba('por_aprobar')->es_inicial)->toBeTrue()
        ->and(estadoPrueba('borrador')->es_inicial)->toBeFalse();
});

test('cambiar si una transición exige motivo falla si ya tiene historial, y sin historial se puede', function () {
    sincronizarPrueba();
    $transiciones = DefinicionPrueba::transicionesBase();
    $transiciones[0] = new TransicionDefinida('enviar', 'Enviar', 'borrador', 'enviado', requiereMotivo: true);

    sincronizarPrueba(transiciones: $transiciones);
    expect(transicionPrueba('enviar', 'borrador')->requiere_motivo)->toBeTrue();

    sincronizarPrueba();
    $documento = app(ContextoTransicion::class)->durante(fn () => DocumentoPrueba::query()->forceCreate(['estado_id' => estadoPrueba('enviado')->id, 'nombre' => 'Uno']));
    HistorialEstado::query()->create([
        'documentable_type' => 'documento_prueba', 'documentable_id' => $documento->id,
        'desde_estado_id' => estadoPrueba('borrador')->id, 'hacia_estado_id' => estadoPrueba('enviado')->id,
        'transicion_id' => transicionPrueba('enviar', 'borrador')->id, 'user_id' => app(UsuarioSistema::class)->id(),
    ]);

    expect(fn () => sincronizarPrueba(transiciones: $transiciones))
        ->toThrow(SincronizacionEstadosRechazada::class, 'No se puede cambiar si exige motivo de la transición «Enviar» de documento de prueba');
});

test('cambiar si un estado es de anulación falla si llegan transiciones a él', function () {
    sincronizarPrueba();
    $estados = DefinicionPrueba::estadosBase();
    $estados[3] = new EstadoDefinido('enviado', 'Enviado', TonoEstado::EnCurso, esFinal: true, esCancelacion: true);
    $transiciones = [new TransicionDefinida('enviar', 'Enviar', 'borrador', 'enviado', requiereMotivo: true)];

    expect(fn () => sincronizarPrueba($estados, $transiciones))->toThrow(SincronizacionEstadosRechazada::class, 'No se puede cambiar si «Enviado» de documento de prueba es de anulación');
});

test('un efecto que nadie registró hace fallar al sincronizador', function () {
    $transiciones = [new TransicionDefinida('enviar', 'Enviar', 'borrador', 'enviado', efectos: ['nucleo.prueba.inexistente'])];

    expect(fn () => sincronizarPrueba(transiciones: $transiciones))->toThrow(SincronizacionEstadosRechazada::class, 'usa el efecto «nucleo.prueba.inexistente», que nadie registró');
});

test('una lista de motivos que declara un módulo se crea con su «Otro», una sola vez', function () {
    $registro = new RegistroDocumentos(new RegistroAcciones(app()));
    $registro->familiaDeMotivos('perdida_oportunidad', 'Pérdida de oportunidad');
    (new SincronizadorEstados($registro))->sincronizar();
    (new SincronizadorEstados($registro))->sincronizar();

    $familia = TipoMotivo::query()->where('codigo', 'perdida_oportunidad')->firstOrFail();
    expect(Motivo::query()->where('tipo_motivo_id', $familia->id)->pluck('codigo')->all())->toBe(['otro']);
});

test('los motivos con que nace una lista se crean una sola vez y no pisan lo que se cambió en Configuración', function () {
    $registro = new RegistroDocumentos(new RegistroAcciones(app()));
    $registro->familiaDeMotivos('perdida_oportunidad', 'Pérdida de oportunidad', [
        new MotivoInicial('precio', 'Precio alto'),
        new MotivoInicial('pedido', 'El cliente cambió lo pedido', esCambioCliente: true),
    ]);
    (new SincronizadorEstados($registro))->sincronizar();
    $familia = TipoMotivo::query()->where('codigo', 'perdida_oportunidad')->firstOrFail();
    $precio = Motivo::query()->where('tipo_motivo_id', $familia->id)->where('codigo', 'precio')->sole();
    $precio->update(['nombre' => 'Precio fuera de mercado', 'es_activo' => false]);

    (new SincronizadorEstados($registro))->sincronizar();
    $motivos = Motivo::query()->where('tipo_motivo_id', $familia->id)->orderBy('codigo')->get();

    expect($motivos->pluck('codigo')->all())->toBe(['otro', 'pedido', 'precio'])
        ->and($motivos->firstWhere('codigo', 'precio')->only(['nombre', 'es_activo']))->toBe(['nombre' => 'Precio fuera de mercado', 'es_activo' => false])
        ->and($motivos->firstWhere('codigo', 'pedido')->es_cambio_cliente)->toBeTrue();
});

test('el núcleo siembra los estados de los módulos con el seeder', function () {
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba);

    $this->seed(EstadosSeeder::class);

    expect(estadoPrueba('borrador')->es_inicial)->toBeTrue();
});
