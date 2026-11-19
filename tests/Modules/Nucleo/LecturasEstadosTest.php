<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\TonoEstado;
use App\Modules\Nucleo\Estados\EstadoEnBarra;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Estados\SalidaDeEstado;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\EstadosService;
use App\Modules\Nucleo\Services\MotivosService;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;
use Tests\Modules\Nucleo\Fixtures\FuenteDeMotivosPrueba;
use Tests\Modules\Nucleo\Fixtures\MontoHastaMil;

/*
 * Lo que leen las pantallas de un documento con estados (plan 1.5, D10): la barra de estados, el historial y la
 * consulta de los «Otro». Todo con el documento de prueba.
 */

beforeEach(function () {
    test()->seed(ParametrosSeeder::class);
    DocumentoPrueba::crearTabla();
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba(transiciones: [
        new TransicionDefinida('enviar', 'Enviar', 'borrador', 'enviado', condiciones: [MontoHastaMil::class]),
        new TransicionDefinida('pedir_aprobacion', 'Pedir aprobación', 'borrador', 'por_aprobar'),
        new TransicionDefinida('aprobar', 'Aprobar', 'por_aprobar', 'aprobado', requiereAprobacion: true),
        new TransicionDefinida('rechazar', 'Rechazar', 'por_aprobar', 'anulado', requiereMotivo: true),
        new TransicionDefinida('anular', 'Anular', ['borrador', 'enviado'], 'anulado', requiereMotivo: true),
        new TransicionDefinida('registrar_adenda', 'Registrar adenda', 'borrador', 'borrador', accionPropia: true),
    ]));
    app(SincronizadorEstados::class)->sincronizar();
});

function lePersona(string ...$permisos): User
{
    foreach ($permisos as $permiso) {
        Permission::findOrCreate($permiso);
    }

    return User::factory()->create(['name' => 'Ana Ejemplo'])->givePermissionTo($permisos);
}

function leDocumento(array $datos = []): DocumentoPrueba
{
    return app(TransicionesService::class)->crear(new DocumentoPrueba(['nombre' => 'Pedido de cables', ...$datos]), User::factory()->create(['name' => 'Bruno Ejemplo']))->refresh();
}

function leOtro(string $familia): Motivo
{
    return Motivo::query()->whereHas('tipoMotivo', fn ($q) => $q->where('codigo', $familia))->where('es_otro', true)->sole();
}

function leAnularConOtro(string $detalle): void
{
    $documento = leDocumento();
    app(TransicionesService::class)->transicionar($documento, 'anular', 1, lePersona('nucleo.prueba.anular'), new MotivoElegido(leOtro('nucleo.prueba.anular')->id, $detalle));
}

// --- Barra de estados ---

test('la barra da los estados en orden con el actual y solo las salidas que la persona puede usar', function () {
    $documento = leDocumento();

    $barra = app(EstadosService::class)->barraDeEstados($documento, lePersona('nucleo.prueba.enviar', 'nucleo.prueba.registrar_adenda'));

    expect(array_map(fn (EstadoEnBarra $e): string => $e->codigo, $barra->estados))->toBe(['borrador', 'por_aprobar', 'aprobado', 'enviado', 'anulado'])
        ->and($barra->actual()->codigo)->toBe('borrador')
        ->and($barra->actual()->tono)->toBe(TonoEstado::Neutro)
        ->and(array_map(fn (SalidaDeEstado $s): array => [$s->codigo, $s->hacia, $s->accion], $barra->salidas))
        ->toBe([['enviar', 'Enviado', 'nucleo.prueba.enviar'], ['registrar_adenda', 'Borrador', null]])
        ->and($barra->esperanAprobacion)->toBe([]);
});

test('la barra no ofrece una salida cuya condición no se cumple ni a quien no tiene permiso', function () {
    $alto = leDocumento(['monto' => '1500.00']);
    $persona = lePersona('nucleo.prueba.enviar', 'nucleo.prueba.anular');

    expect(array_map(fn (SalidaDeEstado $s): string => $s->codigo, app(EstadosService::class)->barraDeEstados($alto, $persona)->salidas))->toBe(['anular'])
        ->and(app(EstadosService::class)->barraDeEstados($alto, User::factory()->create())->salidas)->toBe([]);
});

test('en «Por aprobar» la barra muestra lo que espera aprobación, aparte de las salidas', function () {
    $documento = leDocumento();
    app(TransicionesService::class)->transicionar($documento, 'pedir_aprobacion', 1, lePersona('nucleo.prueba.pedir_aprobacion'));

    $barra = app(EstadosService::class)->barraDeEstados($documento, lePersona('nucleo.prueba.rechazar'));

    expect($barra->actual()->codigo)->toBe('por_aprobar')
        ->and($barra->esperanAprobacion)->toBe(['Aprobar'])
        ->and(array_map(fn (SalidaDeEstado $s): array => [$s->codigo, $s->requiereMotivo, $s->anula], $barra->salidas))->toBe([['rechazar', true, true]]);
});

// --- Historial ---

test('el historial va del primer cambio al último con motivo, detalle, comentario y quién', function () {
    $documento = leDocumento();
    $persona = lePersona('nucleo.prueba.anular');
    app(TransicionesService::class)->transicionar($documento, 'anular', 1, $persona, new MotivoElegido(leOtro('nucleo.prueba.anular')->id, 'El cliente cambió de idea'), 'Llamó por teléfono');

    $historial = app(EstadosService::class)->historialDe($documento);

    expect($historial)->toHaveCount(2)
        ->and([$historial[0]->desde, $historial[0]->hacia, $historial[0]->transicion, $historial[0]->usuario])->toBe([null, 'Borrador', null, 'Bruno Ejemplo'])
        ->and([$historial[1]->desde, $historial[1]->hacia, $historial[1]->transicion, $historial[1]->motivo, $historial[1]->motivoDetalle, $historial[1]->comentario, $historial[1]->usuario])
        ->toBe(['Borrador', 'Anulado', 'Anular', 'Otro', 'El cliente cambió de idea', 'Llamó por teléfono', 'Ana Ejemplo'])
        ->and($historial[1]->originadoPor)->toBeNull();
});

// --- Detalles de «Otro» ---

test('«Precio alto», «precio  ALTO» y «precio álto» cuentan como uno, sin mezclar familias', function () {
    foreach (['Precio alto', 'precio  ALTO', 'precio álto', 'Plazo muy largo'] as $detalle) {
        leAnularConOtro($detalle);
    }
    $documento = leDocumento();
    app(TransicionesService::class)->transicionar($documento, 'pedir_aprobacion', 1, lePersona('nucleo.prueba.pedir_aprobacion'));
    app(TransicionesService::class)->transicionar($documento, 'rechazar', 2, lePersona('nucleo.prueba.rechazar'), new MotivoElegido(leOtro('nucleo.prueba.rechazar')->id, 'Precio alto'));

    $detalles = app(MotivosService::class)->detallesDeOtro('nucleo.prueba.anular');

    expect(array_map(fn ($d): array => [$d->clave, $d->texto, $d->veces], $detalles))
        ->toBe([['precio alto', 'precio álto', 3], ['plazo muy largo', 'Plazo muy largo', 1]])
        ->and(app(MotivosService::class)->detallesDeOtro('nucleo.prueba.rechazar')[0]->veces)->toBe(1);
});

test('el filtro por fecha deja fuera los «Otro» anteriores', function () {
    leAnularConOtro('Precio alto');
    leAnularConOtro('Precio alto');
    // Un uso viejo: el historial solo se inserta, así que la fecha se retrocede a mano.
    DB::table('historial_estados')->where('motivo_es_otro', true)->orderBy('id')->limit(1)->update(['fecha' => '2026-01-10 10:00:00-05']);

    $detalles = app(MotivosService::class)->detallesDeOtro('nucleo.prueba.anular', CarbonImmutable::parse('2026-06-01'));

    expect($detalles[0]->veces)->toBe(1)
        ->and(app(MotivosService::class)->detallesDeOtro('nucleo.prueba.anular')[0]->veces)->toBe(2);
});

test('una FuenteDeMotivos de otro módulo se suma a la consulta', function () {
    app(RegistroDocumentos::class)->fuenteDeMotivos(FuenteDeMotivosPrueba::class);
    leAnularConOtro('Precio alto');

    $detalles = app(MotivosService::class)->detallesDeOtro('nucleo.prueba.anular');

    expect([$detalles[0]->clave, $detalles[0]->texto, $detalles[0]->veces])->toBe(['precio alto', 'Precio alto', 3])
        ->and(TipoMotivo::query()->where('codigo', 'nucleo.prueba.rechazar')->exists())->toBeTrue()
        ->and(app(MotivosService::class)->detallesDeOtro('nucleo.prueba.rechazar'))->toBe([]);
});
