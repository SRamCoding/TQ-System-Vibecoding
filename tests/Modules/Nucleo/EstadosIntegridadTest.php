<?php

declare(strict_types=1);

use App\Modules\Nucleo\Services\UsuarioSistema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * La base protege estados, transiciones, historial, efectos aplicados y motivos aunque se salte el servicio
 * (regla 5; plan 1.5, D2, D3 y D9). Las filas se escriben por query builder: los modelos llegan en el paso 2.
 */

/**
 * @param  array<string, mixed>  $cambios
 */
function estadoCrudo(string $documento, string $codigo, array $cambios = []): int
{
    return DB::table('estados')->insertGetId([
        'documento_type' => $documento, 'codigo' => $codigo, 'nombre' => ucfirst($codigo), 'color' => 'neutro',
        'orden' => 1, 'created_by' => app(UsuarioSistema::class)->id(), ...$cambios,
    ]);
}

/**
 * @param  array<string, mixed>  $cambios
 */
function transicionCruda(string $documento, int $desde, int $hacia, string $codigo, array $cambios = []): int
{
    return DB::table('transiciones_estado')->insertGetId([
        'documento_type' => $documento, 'codigo' => $codigo, 'nombre' => ucfirst($codigo), 'orden' => 1,
        'desde_estado_id' => $desde, 'hacia_estado_id' => $hacia, 'permiso' => "prueba.{$codigo}",
        'created_by' => app(UsuarioSistema::class)->id(), ...$cambios,
    ]);
}

/**
 * @return array{familia: int, precio: int, otro: int}
 */
function familiaCruda(string $codigo): array
{
    $sistema = app(UsuarioSistema::class)->id();
    $familia = DB::table('tipos_motivo')->insertGetId(['codigo' => $codigo, 'nombre' => $codigo, 'created_by' => $sistema]);

    return [
        'familia' => $familia,
        'precio' => DB::table('motivos')->insertGetId(['tipo_motivo_id' => $familia, 'codigo' => 'precio', 'nombre' => 'Precio', 'nombre_comparable' => 'precio', 'created_by' => $sistema]),
        'otro' => DB::table('motivos')->insertGetId(['tipo_motivo_id' => $familia, 'codigo' => 'otro', 'nombre' => 'Otro', 'nombre_comparable' => 'otro', 'es_otro' => true, 'created_by' => $sistema]),
    ];
}

/**
 * Documento de prueba con borrador → enviado (sin motivo) y borrador → anulado (con motivo, familia propia).
 *
 * @return array{borrador: int, enviado: int, anulado: int, enviar: int, anular: int, motivos: array{familia: int, precio: int, otro: int}}
 */
function maquinaCruda(): array
{
    $borrador = estadoCrudo('prueba', 'borrador', ['es_inicial' => true, 'es_editable' => true]);
    $enviado = estadoCrudo('prueba', 'enviado', ['orden' => 2]);
    $anulado = estadoCrudo('prueba', 'anulado', ['orden' => 3, 'es_final' => true, 'es_cancelacion' => true, 'color' => 'anulado']);
    $motivos = familiaCruda('prueba.anular');

    return [
        'borrador' => $borrador, 'enviado' => $enviado, 'anulado' => $anulado, 'motivos' => $motivos,
        'enviar' => transicionCruda('prueba', $borrador, $enviado, 'enviar'),
        'anular' => transicionCruda('prueba', $borrador, $anulado, 'anular', [
            'hacia_es_cancelacion' => true, 'requiere_motivo' => true, 'tipo_motivo_id' => $motivos['familia'],
        ]),
    ];
}

/**
 * @param  array<string, mixed>  $cambios
 */
function historialCrudo(array $cambios): int
{
    return DB::table('historial_estados')->insertGetId([
        'documentable_type' => 'prueba', 'documentable_id' => 1, 'user_id' => app(UsuarioSistema::class)->id(), ...$cambios,
    ]);
}

/**
 * Fila de «anular» con el motivo «Precio», válida salvo lo que se cambie.
 *
 * @param  array<string, mixed>  $m
 * @param  array<string, mixed>  $cambios
 */
function historialAnularCrudo(array $m, array $cambios = []): int
{
    return historialCrudo([
        'desde_estado_id' => $m['borrador'], 'hacia_estado_id' => $m['anulado'], 'transicion_id' => $m['anular'],
        'requiere_motivo' => true, 'tipo_motivo_id' => $m['motivos']['familia'], 'motivo_id' => $m['motivos']['precio'],
        ...$cambios,
    ]);
}

/**
 * La falla esperada corre en un punto de guardado: así no aborta la transacción de la prueba y la siguiente
 * expectativa se puede revisar.
 */
function sinAbortar(Closure $tarea): Closure
{
    return fn () => DB::transaction($tarea);
}

// --- Estados y transiciones (D2) ---

test('una máquina válida se guarda con su historial y sus efectos', function () {
    $m = maquinaCruda();
    historialCrudo(['hacia_estado_id' => $m['borrador']]);
    $envio = historialCrudo(['desde_estado_id' => $m['borrador'], 'hacia_estado_id' => $m['enviado'], 'transicion_id' => $m['enviar']]);
    historialAnularCrudo($m, ['motivo_id' => $m['motivos']['otro'], 'motivo_es_otro' => true, 'motivo_detalle' => 'El cliente cambió de idea', 'comentario' => 'Llamó por teléfono']);
    DB::table('efectos_aplicados')->insert(['historial_estado_id' => $envio, 'clave' => 'prueba.aviso', 'orden' => 1, 'es_externo' => true]);

    expect(DB::table('historial_estados')->count())->toBe(3)
        ->and(DB::table('efectos_aplicados')->value('datos'))->toBe('{}');
});

test('un documento tiene un solo estado inicial y no repite códigos', function () {
    estadoCrudo('prueba', 'borrador', ['es_inicial' => true]);

    expect(sinAbortar(fn () => estadoCrudo('prueba', 'otro_inicial', ['es_inicial' => true])))->toThrow(QueryException::class, 'estados_un_inicial_por_documento')
        ->and(sinAbortar(fn () => estadoCrudo('prueba', 'borrador')))->toThrow(QueryException::class, 'estados_documento_type_codigo_unique')
        ->and(estadoCrudo('otro_documento', 'borrador', ['es_inicial' => true]))->toBeInt();
});

test('el color es un tono de la lista', function () {
    expect(sinAbortar(fn () => estadoCrudo('prueba', 'rojo', ['color' => '#ff0000'])))->toThrow(QueryException::class, 'estados_color_check');
});

test('un estado de cancelación es final y no editable', function () {
    expect(sinAbortar(fn () => estadoCrudo('prueba', 'anulado', ['es_cancelacion' => true])))->toThrow(QueryException::class, 'estados_cancelacion_check')
        ->and(sinAbortar(fn () => estadoCrudo('prueba', 'anulado', ['es_cancelacion' => true, 'es_final' => true, 'es_editable' => true])))->toThrow(QueryException::class, 'estados_cancelacion_check');
});

test('una transición entre estados de dos documentos falla', function () {
    $borrador = estadoCrudo('prueba', 'borrador');
    $ajeno = estadoCrudo('otro_documento', 'enviado');

    expect(sinAbortar(fn () => transicionCruda('prueba', $borrador, $ajeno, 'enviar')))->toThrow(QueryException::class, 'transiciones_estado_hacia_foreign_documento')
        ->and(sinAbortar(fn () => transicionCruda('otro_documento', $borrador, $ajeno, 'enviar')))->toThrow(QueryException::class, 'transiciones_estado_desde_foreign_documento');
});

test('una transición a cancelación sin motivo falla, y la copia de cancelación no puede mentir', function () {
    $borrador = estadoCrudo('prueba', 'borrador');
    $anulado = estadoCrudo('prueba', 'anulado', ['es_final' => true, 'es_cancelacion' => true]);

    expect(sinAbortar(fn () => transicionCruda('prueba', $borrador, $anulado, 'anular', ['hacia_es_cancelacion' => true])))->toThrow(QueryException::class, 'transiciones_estado_cancelacion_motivo_check')
        ->and(sinAbortar(fn () => transicionCruda('prueba', $borrador, $anulado, 'anular')))->toThrow(QueryException::class, 'transiciones_estado_hacia_foreign_documento');
});

test('una transición exige motivo si y solo si tiene su familia', function () {
    $borrador = estadoCrudo('prueba', 'borrador');
    $enviado = estadoCrudo('prueba', 'enviado');
    $familia = familiaCruda('prueba.enviar')['familia'];

    expect(sinAbortar(fn () => transicionCruda('prueba', $borrador, $enviado, 'enviar', ['requiere_motivo' => true])))->toThrow(QueryException::class, 'transiciones_estado_motivo_check')
        ->and(sinAbortar(fn () => transicionCruda('prueba', $borrador, $enviado, 'enviar', ['tipo_motivo_id' => $familia])))->toThrow(QueryException::class, 'transiciones_estado_motivo_check');
});

test('una transición con aprobación no tiene permiso', function () {
    $porAprobar = estadoCrudo('prueba', 'por_aprobar');
    $aprobado = estadoCrudo('prueba', 'aprobado');

    expect(sinAbortar(fn () => transicionCruda('prueba', $porAprobar, $aprobado, 'aprobar', ['requiere_aprobacion' => true])))->toThrow(QueryException::class, 'transiciones_estado_aprobacion_check')
        ->and(transicionCruda('prueba', $porAprobar, $aprobado, 'aprobar', ['requiere_aprobacion' => true, 'permiso' => null]))->toBeInt();
});

test('una transición no repite su código desde el mismo estado', function () {
    $borrador = estadoCrudo('prueba', 'borrador');
    $enviado = estadoCrudo('prueba', 'enviado');
    transicionCruda('prueba', $borrador, $enviado, 'enviar');

    expect(sinAbortar(fn () => transicionCruda('prueba', $borrador, $borrador, 'enviar')))->toThrow(QueryException::class, 'transiciones_estado_codigo_unique');
});

// --- Historial (D2, D9) ---

test('un historial sin desde con transición falla, y con desde sin transición también', function () {
    $m = maquinaCruda();

    expect(sinAbortar(fn () => historialCrudo(['hacia_estado_id' => $m['enviado'], 'transicion_id' => $m['enviar']])))->toThrow(QueryException::class, 'historial_estados_transicion_check')
        ->and(sinAbortar(fn () => historialCrudo(['desde_estado_id' => $m['borrador'], 'hacia_estado_id' => $m['enviado']])))->toThrow(QueryException::class, 'historial_estados_transicion_check');
});

test('el historial no registra un salto que la transición no hace', function () {
    $m = maquinaCruda();

    expect(sinAbortar(fn () => historialCrudo(['desde_estado_id' => $m['enviado'], 'hacia_estado_id' => $m['enviado'], 'transicion_id' => $m['enviar']])))
        ->toThrow(QueryException::class, 'historial_estados_transicion_foreign_copia');
});

test('el estado del historial es del mismo tipo de documento', function () {
    $m = maquinaCruda();

    expect(sinAbortar(fn () => historialCrudo(['documentable_type' => 'otro_documento', 'hacia_estado_id' => $m['borrador']])))
        ->toThrow(QueryException::class, 'historial_estados_hacia_foreign_documento');
});

test('un historial sin motivo en una transición que lo exige falla, y la copia no puede mentir', function () {
    $m = maquinaCruda();

    expect(sinAbortar(fn () => historialAnularCrudo($m, ['requiere_motivo' => true, 'tipo_motivo_id' => null, 'motivo_id' => null])))->toThrow(QueryException::class, 'historial_estados_motivo_check')
        ->and(sinAbortar(fn () => historialAnularCrudo($m, ['requiere_motivo' => false, 'tipo_motivo_id' => null, 'motivo_id' => null])))->toThrow(QueryException::class, 'historial_estados_transicion_foreign_copia');
});

test('un motivo en una transición que no lo exige falla', function () {
    $m = maquinaCruda();

    expect(sinAbortar(fn () => historialCrudo([
        'desde_estado_id' => $m['borrador'], 'hacia_estado_id' => $m['enviado'], 'transicion_id' => $m['enviar'],
        'tipo_motivo_id' => $m['motivos']['familia'], 'motivo_id' => $m['motivos']['precio'],
    ])))->toThrow(QueryException::class, 'historial_estados_motivo_check');
});

test('un motivo de otra familia falla', function () {
    $m = maquinaCruda();
    $ajena = familiaCruda('prueba.rechazar');

    expect(sinAbortar(fn () => historialAnularCrudo($m, ['motivo_id' => $ajena['precio']])))->toThrow(QueryException::class, 'historial_estados_motivo_foreign_familia')
        ->and(sinAbortar(fn () => historialAnularCrudo($m, ['tipo_motivo_id' => $ajena['familia'], 'motivo_id' => $ajena['precio']])))->toThrow(QueryException::class, 'historial_estados_transicion_foreign_familia');
});

test('«Otro» sin detalle falla, y la marca de «Otro» la pone el motivo', function () {
    $m = maquinaCruda();

    expect(sinAbortar(fn () => historialAnularCrudo($m, ['motivo_id' => $m['motivos']['otro'], 'motivo_es_otro' => true])))->toThrow(QueryException::class, 'historial_estados_detalle_check')
        ->and(sinAbortar(fn () => historialAnularCrudo($m, ['motivo_id' => $m['motivos']['otro'], 'motivo_es_otro' => true, 'motivo_detalle' => '   '])))->toThrow(QueryException::class, 'historial_estados_detalle_check')
        ->and(sinAbortar(fn () => historialAnularCrudo($m, ['motivo_id' => $m['motivos']['otro']])))->toThrow(QueryException::class, 'historial_estados_motivo_foreign_familia')
        ->and(sinAbortar(fn () => historialAnularCrudo($m, ['motivo_es_otro' => true, 'motivo_detalle' => 'Detalle suelto'])))->toThrow(QueryException::class, 'historial_estados_motivo_foreign_familia');
});

test('un comentario en blanco falla', function () {
    $m = maquinaCruda();

    expect(sinAbortar(fn () => historialAnularCrudo($m, ['comentario' => ' '])))->toThrow(QueryException::class, 'historial_estados_comentario_check');
});

// --- Motivos (D9) ---

test('cada familia tiene un solo «Otro», que no se desactiva', function () {
    $m = familiaCruda('prueba.anular');

    expect(sinAbortar(fn () => DB::table('motivos')->insert(['tipo_motivo_id' => $m['familia'], 'codigo' => 'otro_mas', 'nombre' => 'Otro más', 'nombre_comparable' => 'otro mas', 'es_otro' => true, 'created_by' => app(UsuarioSistema::class)->id()])))
        ->toThrow(QueryException::class, 'motivos_un_otro_por_familia')
        ->and(sinAbortar(fn () => DB::table('motivos')->where('id', $m['otro'])->update(['es_activo' => false])))->toThrow(QueryException::class, 'motivos_otro_activo_check')
        ->and(DB::table('motivos')->where('id', $m['precio'])->update(['es_activo' => false]))->toBe(1);
});

test('un motivo no repite su código en la familia', function () {
    $m = familiaCruda('prueba.anular');

    expect(sinAbortar(fn () => DB::table('motivos')->insert(['tipo_motivo_id' => $m['familia'], 'codigo' => 'precio', 'nombre' => 'Precio otra vez', 'nombre_comparable' => 'precio otra vez', 'created_by' => app(UsuarioSistema::class)->id()])))
        ->toThrow(QueryException::class, 'motivos_tipo_motivo_id_codigo_unique');
});

test('dos motivos de una familia no se llaman igual sin contar mayúsculas, tildes ni espacios', function () {
    $m = familiaCruda('prueba.anular');
    $motivo = fn (string $codigo, string $nombre, string $comparable): int => DB::table('motivos')->insertGetId([
        'tipo_motivo_id' => $m['familia'], 'codigo' => $codigo, 'nombre' => $nombre, 'nombre_comparable' => $comparable, 'created_by' => app(UsuarioSistema::class)->id(),
    ]);

    expect(sinAbortar(fn () => $motivo('precio_alto', 'Precio  ÁLTO', 'Precio  ÁLTO')))->toThrow(QueryException::class, 'motivos_nombre_comparable_check')
        ->and(sinAbortar(fn () => $motivo('precio_2', ' PRECIO ', 'precio')))->toThrow(QueryException::class, 'motivos_tipo_motivo_id_nombre_comparable_unique')
        ->and($motivo('precio_alto', 'Precio  ÁLTO', 'precio alto'))->toBeInt();
});

test('un «Otro» en el historial siempre lleva su motivo', function () {
    $m = maquinaCruda();

    expect(sinAbortar(fn () => historialCrudo(['hacia_estado_id' => $m['borrador'], 'motivo_es_otro' => true, 'motivo_detalle' => 'Sin motivo elegido'])))
        ->toThrow(QueryException::class, 'historial_estados_otro_check');
});

// --- Efectos aplicados (D3) ---

test('un efecto interno se guarda ya ejecutado, con datos de objeto y revertido por una fila posterior', function () {
    $m = maquinaCruda();
    $creacion = historialCrudo(['hacia_estado_id' => $m['borrador']]);
    $efecto = ['historial_estado_id' => $creacion, 'clave' => 'prueba.stock', 'orden' => 1];

    expect(sinAbortar(fn () => DB::table('efectos_aplicados')->insert($efecto)))->toThrow(QueryException::class, 'efectos_aplicados_interno_ejecutado_check')
        ->and(sinAbortar(fn () => DB::table('efectos_aplicados')->insert([...$efecto, 'ejecutado_at' => now(), 'datos' => '[1]'])))->toThrow(QueryException::class, 'efectos_aplicados_datos_check')
        ->and(sinAbortar(fn () => DB::table('efectos_aplicados')->insert([...$efecto, 'ejecutado_at' => now(), 'revertido_por_historial_id' => $creacion])))->toThrow(QueryException::class, 'efectos_aplicados_revertido_check')
        // Solo un externo que salió y se anuló tiene su reversión ejecutada.
        ->and(sinAbortar(fn () => DB::table('efectos_aplicados')->insert([...$efecto, 'ejecutado_at' => now(), 'reversion_ejecutada_at' => now()])))->toThrow(QueryException::class, 'efectos_aplicados_reversion_check')
        ->and(sinAbortar(fn () => DB::table('efectos_aplicados')->insert([...$efecto, 'es_externo' => true, 'reversion_ejecutada_at' => now()])))->toThrow(QueryException::class, 'efectos_aplicados_reversion_check')
        // La reserva es de un externo sin ejecutar; la de reversión, de uno revertido.
        ->and(sinAbortar(fn () => DB::table('efectos_aplicados')->insert([...$efecto, 'ejecutado_at' => now(), 'reservado_hasta' => now()])))->toThrow(QueryException::class, 'efectos_aplicados_reserva_check')
        ->and(sinAbortar(fn () => DB::table('efectos_aplicados')->insert([...$efecto, 'es_externo' => true, 'reversion_reservada_hasta' => now()])))->toThrow(QueryException::class, 'efectos_aplicados_reserva_check');

    DB::table('efectos_aplicados')->insert([...$efecto, 'ejecutado_at' => now()]);

    expect(sinAbortar(fn () => DB::table('efectos_aplicados')->insert([...$efecto, 'ejecutado_at' => now()])))->toThrow(QueryException::class, 'efectos_aplicados_historial_estado_id_orden_unique');
});
