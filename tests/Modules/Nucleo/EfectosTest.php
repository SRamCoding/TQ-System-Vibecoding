<?php

declare(strict_types=1);

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\Alertas\RegistroAlertas;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Estados\RevisionAntesDeAnular;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use App\Modules\Nucleo\Excepciones\SincronizacionEstadosRechazada;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Jobs\EjecutarEfectoExterno;
use App\Modules\Nucleo\Jobs\RevertirEfectoExterno;
use App\Modules\Nucleo\Models\EfectoAplicado;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\Notificacion;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Notificaciones\RegistroAvisos;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\ContextoEfectos;
use App\Modules\Nucleo\Services\EfectosExternosService;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\TransicionesService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Modules\Nucleo\Fixtures\AvisoDePrueba;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DependientesDePrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;
use Tests\Modules\Nucleo\Fixtures\EfectoA;
use Tests\Modules\Nucleo\Fixtures\EfectoB;
use Tests\Modules\Nucleo\Fixtures\EfectoC;
use Tests\Modules\Nucleo\Fixtures\EfectoQueFalla;

/*
 * Efectos de un cambio de estado (plan 1.5, D3 y D4): los internos corren en la transacción y guardan lo que
 * hicieron; los externos, por cola después del commit y una sola vez; anular revierte los vivos en orden inverso con
 * esos datos, y no se anula con documentos vivos que dependen. Todo con el documento de prueba, sin pantalla.
 */

/**
 * @return list<TransicionDefinida>
 */
function transicionesConEfectos(): array
{
    return [
        new TransicionDefinida('pedir_aprobacion', 'Pedir aprobación', 'borrador', 'por_aprobar', efectos: ['nucleo.prueba.a']),
        new TransicionDefinida('aprobar', 'Aprobar', 'por_aprobar', 'aprobado', efectos: ['nucleo.prueba.b', 'nucleo.prueba.c']),
        new TransicionDefinida('enviar', 'Enviar', 'aprobado', 'enviado', efectos: ['nucleo.prueba.aviso']),
        new TransicionDefinida('enviar_ya', 'Enviar ya', 'borrador', 'enviado', efectos: ['nucleo.prueba.aviso', 'nucleo.prueba.a']),
        new TransicionDefinida('fallar', 'Fallar', 'borrador', 'enviado', efectos: ['nucleo.prueba.aviso', 'nucleo.prueba.a', 'nucleo.prueba.falla']),
        new TransicionDefinida('anular', 'Anular', ['borrador', 'por_aprobar', 'aprobado', 'enviado'], 'anulado', requiereMotivo: true),
    ];
}

/**
 * @param  list<class-string<RevisionAntesDeAnular>>  $revisiones
 */
function registrarDocumentoConEfectos(array $revisiones = []): void
{
    $registro = app(RegistroDocumentos::class);
    $registro->efecto('nucleo.prueba.a', EfectoA::class);
    $registro->efecto('nucleo.prueba.b', EfectoB::class);
    $registro->efecto('nucleo.prueba.c', EfectoC::class);
    $registro->efecto('nucleo.prueba.falla', EfectoQueFalla::class);
    $registro->efecto('nucleo.prueba.aviso', AvisoDePrueba::class);
    $registro->registrar(new DefinicionPrueba(transiciones: transicionesConEfectos(), revisiones: $revisiones));
}

beforeEach(function () {
    test()->seed(ParametrosSeeder::class);
    DocumentoPrueba::crearTabla();
    AvisoDePrueba::$nivelDeTransaccion = null;
    AvisoDePrueba::$ultimaClave = null;
    AvisoDePrueba::$fallar = 0;
});

function quienHaceTodo(): User
{
    $permisos = array_map(fn (string $codigo): string => "nucleo.prueba.{$codigo}", ['pedir_aprobacion', 'aprobar', 'enviar', 'enviar_ya', 'fallar', 'anular']);

    foreach ($permisos as $permiso) {
        Permission::findOrCreate($permiso);
    }

    return User::factory()->create()->givePermissionTo($permisos);
}

/**
 * @param  array<string, mixed>  $datos
 */
function documentoConEfectos(array $datos = []): DocumentoPrueba
{
    return app(TransicionesService::class)->crear(new DocumentoPrueba(['nombre' => 'Pedido de cables', 'monto' => '500.00', ...$datos]), User::factory()->create())->refresh();
}

function pasar(DocumentoPrueba $documento, string $codigo, User $persona): HistorialEstado
{
    return app(TransicionesService::class)->transicionar($documento, $codigo, $documento->version, $persona);
}

function anularConMotivo(DocumentoPrueba $documento, User $persona): HistorialEstado
{
    $familia = TipoMotivo::query()->where('codigo', 'nucleo.prueba.anular')->firstOrFail();
    $motivo = Motivo::query()->firstOrCreate(['tipo_motivo_id' => $familia->id, 'codigo' => 'error'], ['nombre' => 'Error']);

    return app(TransicionesService::class)->transicionar($documento, 'anular', $documento->version, $persona, new MotivoElegido($motivo->id));
}

/**
 * @return list<string>
 */
function anotaciones(): array
{
    return DB::table('anotaciones_prueba')->orderBy('id')->pluck('texto')->all();
}

function estadoDe(DocumentoPrueba $documento): string
{
    return Estado::query()->findOrFail($documento->fresh()?->estado_id)->codigo;
}

describe('con el documento de prueba y sus efectos', function () {
    beforeEach(function () {
        registrarDocumentoConEfectos();
        app(SincronizadorEstados::class)->sincronizar();
    });

    test('los internos se aplican en orden y guardan lo que hicieron', function () {
        $documento = documentoConEfectos();
        $persona = quienHaceTodo();

        pasar($documento, 'pedir_aprobacion', $persona);
        $historial = pasar($documento, 'aprobar', $persona);
        $aplicados = $historial->efectos()->orderBy('orden')->get();

        expect(anotaciones())->toBe(['aplicar A 500.00', 'aplicar B 500.00', 'aplicar C 500.00'])
            ->and($aplicados->pluck('clave')->all())->toBe(['nucleo.prueba.b', 'nucleo.prueba.c'])
            ->and($aplicados->pluck('orden')->all())->toBe([1, 2])
            ->and($aplicados->first()?->datos)->toBe(['monto' => '500.00'])
            ->and($aplicados->every(fn (EfectoAplicado $efecto): bool => ! $efecto->es_externo && $efecto->ejecutado_at !== null))->toBeTrue();
    });

    test('un efecto interno que falla deja el documento como estaba y el externo no corre', function () {
        $documento = documentoConEfectos();

        expect(fn () => pasar($documento, 'fallar', quienHaceTodo()))->toThrow(RuntimeException::class, 'El efecto de prueba falló.')
            ->and(estadoDe($documento))->toBe('borrador')
            ->and($documento->fresh()?->version)->toBe(1)
            ->and(HistorialEstado::query()->where('documentable_id', $documento->id)->count())->toBe(1)
            ->and(EfectoAplicado::query()->count())->toBe(0)
            ->and(anotaciones())->toBe([]);
    });

    test('un externo corre después del commit y un reintento no lo repite', function () {
        $documento = documentoConEfectos();

        $historial = pasar($documento, 'enviar_ya', quienHaceTodo());
        $aviso = $historial->efectos()->where('orden', 1)->sole();

        // Declarado antes que A, sale después: el job espera al commit.
        expect(anotaciones())->toBe(['aplicar A 500.00', 'aviso Pedido de cables'])
            ->and($aviso->es_externo)->toBeTrue()
            ->and($aviso->ejecutado_at)->not->toBeNull()
            ->and($aviso->datos)->toBe(['nombre' => 'Pedido de cables']);

        app()->call([new EjecutarEfectoExterno($aviso->id), 'handle']);

        expect(anotaciones())->toBe(['aplicar A 500.00', 'aviso Pedido de cables']);
    });

    test('anular revierte los efectos vivos en orden inverso con los datos guardados aunque el origen cambie', function () {
        $documento = documentoConEfectos();
        $persona = quienHaceTodo();
        pasar($documento, 'pedir_aprobacion', $persona);
        pasar($documento, 'aprobar', $persona);

        // El origen cambió después de aplicar: revertir usa lo guardado, no lo de hoy.
        DB::table('documentos_prueba')->where('id', $documento->id)->update(['monto' => '900.00']);
        $documento->refresh();
        $anulacion = anularConMotivo($documento, $persona);

        expect(array_slice(anotaciones(), 3))->toBe(['revertir C 500.00', 'revertir B 500.00', 'revertir A 500.00'])
            ->and(EfectoAplicado::query()->pluck('revertido_por_historial_id')->unique()->all())->toBe([$anulacion->id]);
    });

    test('anular revierte por cola el externo que ya salió, una sola vez', function () {
        $documento = documentoConEfectos();
        $persona = quienHaceTodo();
        pasar($documento, 'pedir_aprobacion', $persona);
        pasar($documento, 'aprobar', $persona);
        pasar($documento, 'enviar', $persona);

        anularConMotivo($documento, $persona);
        $aviso = EfectoAplicado::query()->where('es_externo', true)->sole();
        app()->call([new RevertirEfectoExterno($aviso->id), 'handle']);

        // Los internos se revierten en la transacción; el externo, por su job después del commit.
        expect(array_slice(anotaciones(), 4))->toBe(['revertir C 500.00', 'revertir B 500.00', 'revertir A 500.00', 'aviso de anulación Pedido de cables'])
            ->and($aviso->fresh()?->reversion_ejecutada_at)->not->toBeNull();
    });

    test('un externo que no llegó a salir no se revierte ni sale después de anular', function () {
        Queue::fake();
        $documento = documentoConEfectos();
        $persona = quienHaceTodo();
        pasar($documento, 'enviar_ya', $persona);

        anularConMotivo($documento, $persona);
        $aviso = EfectoAplicado::query()->where('es_externo', true)->sole();
        app()->call([new EjecutarEfectoExterno($aviso->id), 'handle']);

        Queue::assertNotPushed(RevertirEfectoExterno::class);
        expect(anotaciones())->toBe(['aplicar A 500.00', 'revertir A 500.00'])
            ->and($aviso->fresh()?->ejecutado_at)->toBeNull();
    });

    // --- Efectos externos: reserva y envío fuera de transacción ---

    test('el envío corre sin transacción abierta y con el id del efecto como clave de idempotencia', function () {
        Queue::fake();
        pasar(documentoConEfectos(), 'enviar_ya', quienHaceTodo());
        $aviso = EfectoAplicado::query()->where('es_externo', true)->sole();
        // RefreshDatabase envuelve la prueba en una transacción: «sin transacción» es el mismo nivel que fuera del job.
        $fuera = DB::transactionLevel();

        expect(app(EfectosExternosService::class)->ejecutar($aviso->id))->toBeTrue()
            ->and(AvisoDePrueba::$nivelDeTransaccion)->toBe($fuera)
            ->and(AvisoDePrueba::$ultimaClave)->toBe("efecto-{$aviso->id}")
            ->and($aviso->fresh()?->ejecutado_at)->not->toBeNull()
            ->and($aviso->fresh()?->reservado_hasta)->toBeNull();
    });

    test('un efecto con reserva vigente no lo toma otro intento', function () {
        Queue::fake();
        pasar(documentoConEfectos(), 'enviar_ya', quienHaceTodo());
        $aviso = EfectoAplicado::query()->where('es_externo', true)->sole();
        $aviso->reservado_hasta = now()->addMinute()->toImmutable();
        $aviso->save();

        expect(app(EfectosExternosService::class)->ejecutar($aviso->id))->toBeFalse()
            ->and(anotaciones())->toBe(['aplicar A 500.00'])
            ->and($aviso->fresh()?->ejecutado_at)->toBeNull();
    });

    test('un efecto con reserva vencida se retoma en el siguiente intento', function () {
        Queue::fake();
        pasar(documentoConEfectos(), 'enviar_ya', quienHaceTodo());
        $aviso = EfectoAplicado::query()->where('es_externo', true)->sole();
        // El intento anterior murió a mitad: su reserva venció sin confirmar.
        $aviso->reservado_hasta = now()->subMinute()->toImmutable();
        $aviso->save();

        expect(app(EfectosExternosService::class)->ejecutar($aviso->id))->toBeTrue()
            ->and(anotaciones())->toBe(['aplicar A 500.00', 'aviso Pedido de cables'])
            ->and($aviso->fresh()?->ejecutado_at)->not->toBeNull();
    });

    test('si el envío falla, la reserva se libera y el reintento lo ejecuta', function () {
        Queue::fake();
        pasar(documentoConEfectos(), 'enviar_ya', quienHaceTodo());
        $aviso = EfectoAplicado::query()->where('es_externo', true)->sole();
        AvisoDePrueba::$fallar = 1;

        expect(fn () => app(EfectosExternosService::class)->ejecutar($aviso->id))->toThrow(RuntimeException::class, 'no respondió')
            ->and($aviso->fresh()?->reservado_hasta)->toBeNull()
            ->and($aviso->fresh()?->ejecutado_at)->toBeNull();

        app(EfectosExternosService::class)->ejecutar($aviso->id);

        expect(anotaciones())->toBe(['aplicar A 500.00', 'aviso Pedido de cables'])
            ->and($aviso->fresh()?->ejecutado_at)->not->toBeNull();
    });

    test('un efecto reservado sin confirmar cuenta como salido: impide «Reabrir» y se revierte al anular', function () {
        Queue::fake();
        $documento = documentoConEfectos();
        $persona = quienHaceTodo();
        pasar($documento, 'enviar_ya', $persona);
        $aviso = EfectoAplicado::query()->where('es_externo', true)->sole();
        $transiciones = app(TransicionesService::class);

        expect($transiciones->salioAlExterior($documento))->toBeFalse();

        $aviso->reservado_hasta = now()->subMinute()->toImmutable();
        $aviso->save();

        expect($transiciones->salioAlExterior($documento))->toBeTrue();

        anularConMotivo($documento, $persona);
        Queue::assertPushed(RevertirEfectoExterno::class, fn (RevertirEfectoExterno $job): bool => $job->efectoAplicadoId === $aviso->id);

        // Anulado antes de confirmar: el envío no se retoma, pero su aviso de anulación sí sale.
        expect(app(EfectosExternosService::class)->ejecutar($aviso->id))->toBeTrue()
            ->and(app(EfectosExternosService::class)->revertir($aviso->id))->toBeTrue()
            ->and(array_slice(anotaciones(), -1))->toBe(['aviso de anulación Pedido de cables'])
            ->and(AvisoDePrueba::$ultimaClave)->toBe("efecto-{$aviso->id}-reversion");
    });

    test('al anular, la reversión espera a un envío que sigue en curso', function () {
        Queue::fake();
        $documento = documentoConEfectos();
        $persona = quienHaceTodo();
        pasar($documento, 'enviar_ya', $persona);
        $aviso = EfectoAplicado::query()->where('es_externo', true)->sole();
        $aviso->reservado_hasta = now()->addMinute()->toImmutable();
        $aviso->save();
        anularConMotivo($documento, $persona);

        expect(app(EfectosExternosService::class)->revertir($aviso->id))->toBeFalse()
            ->and($aviso->fresh()?->reversion_ejecutada_at)->toBeNull();
    });

    test('la reserva dura más que el timeout de los jobs y menos que el retry_after de la cola', function () {
        expect((new EjecutarEfectoExterno(1))->timeout)->toBeLessThan(EfectosExternosService::PLAZO_RESERVA)
            ->and((new RevertirEfectoExterno(1))->timeout)->toBeLessThan(EfectosExternosService::PLAZO_RESERVA)
            ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan(EfectosExternosService::PLAZO_RESERVA);
    });

    test('un efecto revertido no se marca revertido otra vez', function () {
        $documento = documentoConEfectos();
        $persona = quienHaceTodo();
        pasar($documento, 'pedir_aprobacion', $persona);
        $anulacion = anularConMotivo($documento, $persona);
        $efecto = EfectoAplicado::query()->sole();

        $efecto->revertido_por_historial_id = $anulacion->id + 1;

        expect(fn () => $efecto->save())->toThrow(LogicException::class, 'solo se marca ejecutado o revertido, una vez');
    });

    test('un efecto que no devuelve datos los guarda como objeto vacío', function () {
        $documento = documentoConEfectos();
        $primera = HistorialEstado::query()->where('documentable_id', $documento->id)->sole();

        $efecto = EfectoAplicado::query()->create(['historial_estado_id' => $primera->id, 'clave' => 'nucleo.prueba.a', 'orden' => 1, 'datos' => [], 'ejecutado_at' => now()]);

        expect(DB::table('efectos_aplicados')->where('id', $efecto->id)->value('datos'))->toBe('{}');
    });

    test('el contexto de efectos se abre durante el efecto y se cierra aunque falle', function () {
        $contexto = app(ContextoEfectos::class);
        $dentro = $contexto->durante(new EfectoA, fn (): bool => $contexto->activo());

        expect($dentro)->toBeTrue()
            ->and(fn () => $contexto->durante(new EfectoQueFalla, fn () => throw new RuntimeException('falla')))->toThrow(RuntimeException::class)
            ->and($contexto->activo())->toBeFalse();
    });

    // --- Plan 1.10, D3: un efecto que agota sus intentos avisa ---

    test('un externo que agota sus intentos avisa una vez a quien hizo el cambio y a los administradores', function () {
        Queue::fake();
        $admin = User::factory()->create()->assignRole(Role::findOrCreate(SeguridadService::ROL_ADMINISTRADOR, 'web'));
        $documento = documentoConEfectos();
        $persona = quienHaceTodo();
        $aviso = pasar($documento, 'enviar_ya', $persona)->efectos()->where('orden', 1)->sole();

        (new EjecutarEfectoExterno($aviso->id))->failed(new RuntimeException('dato del efecto'));
        (new EjecutarEfectoExterno($aviso->id))->failed(new RuntimeException('dato del efecto'));

        $avisos = Notificacion::query()->where('type', AlertasDelNucleo::EFECTO_FALLIDO)->orderBy('notifiable_id')->get();
        $numero = (new DefinicionPrueba)->identificador($documento->fresh() ?? $documento);

        expect($avisos->pluck('notifiable_id')->all())->toBe([$admin->id, $persona->id])
            ->and($avisos->first()?->data['title'])->toBe("No se completó un paso de documento de prueba {$numero}")
            ->and($avisos->first()?->data['body'])->toContain('«Enviar ya»')
            ->and($avisos->first()?->data['body'])->not->toContain('dato del efecto')
            ->and($avisos->first()?->enviar_correo)->toBeTrue();
    });

    test('una reversión que agota sus intentos avisa a quien anuló, aparte del aviso de la ida', function () {
        Queue::fake();
        Role::findOrCreate(SeguridadService::ROL_ADMINISTRADOR, 'web');
        $documento = documentoConEfectos();
        $persona = quienHaceTodo();
        $aviso = pasar($documento, 'enviar_ya', $persona)->efectos()->where('orden', 1)->sole();
        $anula = quienHaceTodo();
        anularConMotivo($documento->fresh() ?? $documento, $anula);

        (new RevertirEfectoExterno($aviso->id))->failed(null);

        $alAnular = Notificacion::query()->where('type', AlertasDelNucleo::EFECTO_FALLIDO)->sole();

        expect($alAnular->notifiable_id)->toBe($anula->id)
            ->and($alAnular->data['body'])->toContain('«revertir Anular»')
            ->and($alAnular->clave_unica)->toBe("efecto:{$aviso->id}:reversion");
    });

    test('si el aviso de un efecto fallido falla, queda en el registro y no tapa el fallo', function () {
        Log::spy();
        $documento = documentoConEfectos();
        $aviso = pasar($documento, 'enviar_ya', quienHaceTodo())->efectos()->where('orden', 1)->sole();
        // Un catálogo sin la alerta: emitirla falla.
        app()->instance(RegistroAlertas::class, new RegistroAlertas(app(RegistroAvisos::class)));

        (new EjecutarEfectoExterno($aviso->id))->failed(null);

        Log::shouldHaveReceived('error')->withArgs(fn (string $mensaje): bool => $mensaje === 'Un efecto externo no se pudo ejecutar.')->once();
        Log::shouldHaveReceived('error')->withArgs(fn (string $mensaje, array $contexto): bool => $mensaje === 'No se pudo avisar que un efecto externo falló.' && $contexto['error'] === InvalidArgumentException::class)->once();
    });
});

// --- Revisiones antes de anular ---

test('con un dependiente vivo anular falla y lo nombra; anulado el dependiente, se puede', function () {
    registrarDocumentoConEfectos([DependientesDePrueba::class]);
    app(SincronizadorEstados::class)->sincronizar();
    $persona = quienHaceTodo();
    $origen = documentoConEfectos();
    $dependiente = documentoConEfectos(['nombre' => 'Recepción', 'origen_id' => $origen->id]);

    expect(fn () => anularConMotivo($origen, $persona))
        ->toThrow(TransicionRechazada::class, "No se puede anular el documento de prueba: lo usan el documento de prueba {$dependiente->numero} (Borrador). Anúlalos primero.")
        ->and(estadoDe($origen))->toBe('borrador');

    anularConMotivo($dependiente, $persona);
    anularConMotivo($origen, $persona);

    expect(estadoDe($origen))->toBe('anulado');
});

test('una revisión registrada por otro módulo también impide anular', function () {
    registrarDocumentoConEfectos();
    app(RegistroDocumentos::class)->revisionAntesDeAnular('nucleo.prueba', DependientesDePrueba::class);
    app(SincronizadorEstados::class)->sincronizar();
    $persona = quienHaceTodo();
    $origen = documentoConEfectos();
    $primero = documentoConEfectos(['origen_id' => $origen->id]);
    $segundo = documentoConEfectos(['origen_id' => $origen->id]);

    expect(fn () => anularConMotivo($origen, $persona))
        ->toThrow(TransicionRechazada::class, "lo usan el documento de prueba {$primero->numero} (Borrador) y el documento de prueba {$segundo->numero} (Borrador).");
});

// --- Sincronizador ---

test('una revisión para un documento sin definición hace fallar al sincronizador', function () {
    registrarDocumentoConEfectos();
    app(RegistroDocumentos::class)->revisionAntesDeAnular('otro.documento', DependientesDePrueba::class);

    expect(fn () => app(SincronizadorEstados::class)->sincronizar())
        ->toThrow(SincronizacionEstadosRechazada::class, 'revisión antes de anular para «otro.documento», que no tiene definición');
});

test('un efecto aplicado y vivo cuya clave ya nadie registra hace fallar al sincronizador', function () {
    registrarDocumentoConEfectos();
    app(SincronizadorEstados::class)->sincronizar();
    pasar(documentoConEfectos(), 'pedir_aprobacion', quienHaceTodo());

    // Otro despliegue: la definición ya no usa efectos y nadie registra «nucleo.prueba.a».
    app()->instance(RegistroDocumentos::class, new RegistroDocumentos(new RegistroAcciones(app())));
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba);

    expect(fn () => app(SincronizadorEstados::class)->sincronizar())
        ->toThrow(SincronizacionEstadosRechazada::class, 'efecto «nucleo.prueba.a» aplicado y nadie lo registra');
});
