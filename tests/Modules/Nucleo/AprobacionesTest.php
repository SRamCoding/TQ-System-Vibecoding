<?php

declare(strict_types=1);

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\Aprobaciones\DatosRegla;
use App\Modules\Nucleo\database\seeders\CatalogosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\EstadoFirmaAprobacion;
use App\Modules\Nucleo\Enums\EstadoRondaAprobacion;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Enums\TipoUnidadOrganizativa;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\AprobacionRechazada;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\FirmaAprobacion;
use App\Modules\Nucleo\Models\HistorialEstado;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\Notificacion;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\ReglaAprobacion;
use App\Modules\Nucleo\Models\RondaAprobacion;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\AprobacionesService;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\TipoCambioService;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;

/*
 * M2 · Aprobaciones (plan 1.7), sin pantalla. El organigrama inventado: Gerencia General arriba, Gerencia de Operaciones
 * debajo y la Jefatura de Proyectos debajo de esta. Las reglas son las de la matriz del v4 con montos de prueba
 * (U1 = 1 000, U2 = 10 000): Gerencia fija los reales (pendiente «Aprobaciones»).
 */

beforeEach(function () {
    test()->seed([ParametrosSeeder::class, CatalogosSeeder::class]);
    DocumentoPrueba::crearTabla();
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba(transiciones: [
        new TransicionDefinida('pedir_aprobacion', 'Pedir aprobación', 'borrador', 'por_aprobar'),
        new TransicionDefinida('aprobar', 'Aprobar', 'por_aprobar', 'aprobado', requiereAprobacion: true),
        new TransicionDefinida('devolver', 'Devolver', 'por_aprobar', 'borrador', esDelSistema: true),
        new TransicionDefinida('anular', 'Anular', ['borrador', 'por_aprobar'], 'anulado', requiereMotivo: true),
    ], alObservar: 'devolver'));
    app(SincronizadorEstados::class)->sincronizar();
    Permission::findOrCreate('nucleo.prueba.pedir_aprobacion');
});

/** Cambia la definición del documento de prueba por otra: un registro nuevo, con la familia de observaciones del núcleo. */
function apRedefinir(DefinicionPrueba $definicion): void
{
    $registro = new RegistroDocumentos(app(RegistroAcciones::class));
    $registro->familiaDeMotivos(AprobacionesService::FAMILIA_OBSERVAR, 'Observaciones al aprobar');
    $registro->registrar($definicion);
    app()->instance(RegistroDocumentos::class, $registro);
    app(SincronizadorEstados::class)->sincronizar();
}

/** Una unidad con su puesto de jefatura ocupado por una persona nueva (o vacante, si `$ocupada` es falso). */
function apUnidad(string $nombre, ?UnidadOrganizativa $padre = null, bool $ocupada = true): UnidadOrganizativa
{
    $unidad = UnidadOrganizativa::factory()->create(['nombre' => $nombre, 'parent_id' => $padre?->id, 'tipo' => TipoUnidadOrganizativa::Gerencia]);
    $puesto = Puesto::factory()->create(['nombre' => "Jefe de {$nombre}", 'unidad_organizativa_id' => $unidad->id]);
    $unidad->update(['jefe_puesto_id' => $puesto->id]);

    if ($ocupada) {
        User::factory()->create(['puesto_id' => $puesto->id]);
    }

    return $unidad->refresh();
}

function apJefe(UnidadOrganizativa $unidad): User
{
    return User::query()->where('puesto_id', $unidad->jefe_puesto_id)->sole();
}

/**
 * @param  list<string>  $pasos
 */
function apRegla(string $nombre, array $pasos, ?string $masDe = null, ?string $hasta = null, ?string $tope = null): int
{
    return app(AprobacionesService::class)->crearRegla(new DatosRegla($nombre, 'documento_prueba', 'aprobar', $masDe, $hasta, $tope, $pasos))->id;
}

/** La matriz del v4 para el requerimiento: hasta U1 su jefatura; sobre U1 también Gerencia de Operaciones. */
function apMatriz(UnidadOrganizativa $operaciones): void
{
    apRegla('Requerimiento: su jefatura', ['documento']);
    apRegla('Requerimiento sobre U1: Gerencia de Operaciones', ["unidad:{$operaciones->id}"], masDe: '1000');
}

/**
 * Crea el documento en la unidad y pide la aprobación a nombre de `$quien`.
 *
 * @param  array<string, mixed>  $datos
 */
function apPedir(UnidadOrganizativa $unidad, string $monto, ?User $quien = null, array $datos = []): DocumentoPrueba
{
    $quien ??= User::factory()->create();
    $quien->givePermissionTo('nucleo.prueba.pedir_aprobacion');
    $documento = app(TransicionesService::class)->crear(new DocumentoPrueba(['nombre' => 'Requerimiento de cables', 'monto' => $monto, 'unidad_id' => $unidad->id, ...$datos]), $quien)->refresh();
    app(TransicionesService::class)->transicionar($documento, 'pedir_aprobacion', $documento->version, $quien);

    return $documento->refresh();
}

function apFirmar(DocumentoPrueba $documento, User $quien): FirmaAprobacion
{
    return app(AprobacionesService::class)->firmar('documento_prueba', $documento->id, 'aprobar', $quien);
}

function apEstado(DocumentoPrueba $documento): string
{
    return Estado::query()->findOrFail($documento->refresh()->estado_id)->codigo;
}

function apObservacion(string $codigo = 'falta-cotizacion'): Motivo
{
    $familia = TipoMotivo::query()->where('codigo', AprobacionesService::FAMILIA_OBSERVAR)->sole();

    return Motivo::query()->firstOrCreate(['tipo_motivo_id' => $familia->id, 'codigo' => $codigo], ['nombre' => 'Falta una cotización']);
}

test('escenario: 800 lo firma solo su jefatura; con su firma, el documento queda aprobado con ella como originadora', function () {
    $general = apUnidad('Gerencia General');
    $operaciones = apUnidad('Gerencia de Operaciones', $general);
    $proyectos = apUnidad('Jefatura de Proyectos', $operaciones);
    apMatriz($operaciones);

    $documento = apPedir($proyectos, '800.00');
    $ronda = RondaAprobacion::query()->sole();

    expect($ronda->firmas()->count())->toBe(1)
        ->and(app(AprobacionesService::class)->porFirmar(apJefe($proyectos)))->toHaveCount(1);

    apFirmar($documento, apJefe($proyectos));

    $ultimo = HistorialEstado::query()->where('documentable_id', $documento->id)->latest('id')->first();
    expect(apEstado($documento))->toBe('aprobado')
        ->and($ultimo->originado_por_user_id)->toBe(apJefe($proyectos)->id)
        ->and($ronda->refresh()->estado)->toBe(EstadoRondaAprobacion::Completa);
});

test('escenario: 5 000 lo firma su jefatura y luego Gerencia de Operaciones, en ese orden', function () {
    $general = apUnidad('Gerencia General');
    $operaciones = apUnidad('Gerencia de Operaciones', $general);
    $proyectos = apUnidad('Jefatura de Proyectos', $operaciones);
    apMatriz($operaciones);
    $documento = apPedir($proyectos, '5000.00');

    expect(fn () => apFirmar($documento, apJefe($operaciones)))
        ->toThrow(AprobacionRechazada::class, 'Esta firma le toca a la jefatura de Jefatura de Proyectos.')
        ->and(fn () => app(TransicionesService::class)->transicionarComoSistema($documento, 'aprobar'))
        ->toThrow(TransicionRechazada::class, 'Falta la firma de la jefatura de Jefatura de Proyectos.');

    apFirmar($documento, apJefe($proyectos));
    expect(apEstado($documento))->toBe('por_aprobar');

    apFirmar($documento->refresh(), apJefe($operaciones));
    expect(apEstado($documento))->toBe('aprobado');
});

test('los montos: «más de» no incluye el límite y «hasta» sí', function () {
    $operaciones = apUnidad('Gerencia de Operaciones');
    $proyectos = apUnidad('Jefatura de Proyectos', $operaciones);
    apMatriz($operaciones);

    apPedir($proyectos, '1000.00');
    apPedir($proyectos, '1000.01');

    expect(RondaAprobacion::query()->orderBy('id')->get()->map(fn (RondaAprobacion $r): int => $r->firmas()->count())->all())->toBe([1, 2]);
});

test('en otra moneda se convierte a soles con el tipo de cambio de venta de hoy', function () {
    $operaciones = apUnidad('Gerencia de Operaciones');
    $proyectos = apUnidad('Jefatura de Proyectos', $operaciones);
    apMatriz($operaciones);
    app(TipoCambioService::class)->registrar('USD', CarbonImmutable::now('America/Lima'), '3.7000', '3.8000', OrigenTipoCambio::Manual);

    apPedir($proyectos, '300.00', datos: ['moneda' => 'USD']);

    $ronda = RondaAprobacion::query()->sole();
    expect($ronda->monto_soles)->toBe('1140.00')
        ->and($ronda->firmas()->count())->toBe(2);
});

test('con la jefatura vacante firma la de arriba', function () {
    $operaciones = apUnidad('Gerencia de Operaciones');
    $proyectos = apUnidad('Jefatura de Proyectos', $operaciones, ocupada: false);
    apRegla('Su jefatura', ['documento']);
    $documento = apPedir($proyectos, '500.00');

    apFirmar($documento, apJefe($operaciones));

    expect(apEstado($documento))->toBe('aprobado');
});

test('vacante en toda la línea: nadie puede firmar y lo dice', function () {
    $proyectos = apUnidad('Jefatura de Proyectos', ocupada: false);
    apRegla('Su jefatura', ['documento']);
    $documento = apPedir($proyectos, '500.00');

    expect(fn () => apFirmar($documento, User::factory()->create()))
        ->toThrow(AprobacionRechazada::class, 'Nadie puede firmar por la jefatura de Jefatura de Proyectos');
});

test('la misma jefatura en dos reglas firma una sola vez', function () {
    $proyectos = apUnidad('Jefatura de Proyectos');
    apRegla('Su jefatura', ['documento']);
    apRegla('Proyectos también', ["unidad:{$proyectos->id}"]);
    $documento = apPedir($proyectos, '500.00');

    expect(RondaAprobacion::query()->sole()->firmas()->count())->toBe(1);

    apFirmar($documento, apJefe($proyectos));

    expect(apEstado($documento))->toBe('aprobado');
});

test('quien pidió la aprobación no firma su propio paso: sube a la jefatura de arriba', function () {
    $operaciones = apUnidad('Gerencia de Operaciones');
    $proyectos = apUnidad('Jefatura de Proyectos', $operaciones);
    apRegla('Su jefatura', ['documento']);
    $documento = apPedir($proyectos, '500.00', apJefe($proyectos));

    expect(fn () => apFirmar($documento, apJefe($proyectos)))->toThrow(AprobacionRechazada::class, 'Esta firma le toca');

    apFirmar($documento, apJefe($operaciones));
    expect(apEstado($documento))->toBe('aprobado');
});

test('autoaprobación: con permiso, bajo el tope de la regla y con motivo; queda marcada y la revisa otra persona', function () {
    $operaciones = apUnidad('Gerencia de Operaciones');
    $proyectos = apUnidad('Jefatura de Proyectos', $operaciones);
    apRegla('Su jefatura', ['documento'], tope: '600');
    $jefe = apJefe($proyectos);
    $documento = apPedir($proyectos, '500.00', $jefe);
    $servicio = app(AprobacionesService::class);

    expect(fn () => $servicio->autoaprobar('documento_prueba', $documento->id, 'aprobar', $jefe, 'Urgente'))
        ->toThrow(AprobacionRechazada::class, 'No tienes permiso');

    Permission::findOrCreate(AprobacionesService::PERMISO_AUTOAPROBAR);
    $jefe->givePermissionTo(AprobacionesService::PERMISO_AUTOAPROBAR);

    expect(fn () => $servicio->autoaprobar('documento_prueba', $documento->id, 'aprobar', $jefe, '  '))
        ->toThrow(AprobacionRechazada::class, 'Di por qué');

    $firma = $servicio->autoaprobar('documento_prueba', $documento->id, 'aprobar', $jefe, 'Obra parada sin este material');

    expect(apEstado($documento))->toBe('aprobado')
        ->and($firma->refresh()->es_autoaprobacion)->toBeTrue()
        ->and(fn () => $servicio->revisarAutoaprobacion($firma->id, $jefe))->toThrow(AprobacionRechazada::class, 'la revisa otra persona');

    $servicio->revisarAutoaprobacion($firma->id, apJefe($operaciones));
    expect($firma->refresh()->revisada_por_user_id)->toBe(apJefe($operaciones)->id);
});

test('autoaprobación: avisa en el momento a Gerencia General, una vez, con quién y qué documento (plan 1.10)', function () {
    Notification::fake();
    $gerente = User::factory()->create()->assignRole(Role::findOrCreate(SeguridadService::ROL_GERENCIA_GENERAL, 'web'));
    $operaciones = apUnidad('Gerencia de Operaciones');
    $proyectos = apUnidad('Jefatura de Proyectos', $operaciones);
    apRegla('Su jefatura', ['documento'], tope: '600');
    $jefe = apJefe($proyectos);
    Permission::findOrCreate(AprobacionesService::PERMISO_AUTOAPROBAR);
    $jefe->givePermissionTo(AprobacionesService::PERMISO_AUTOAPROBAR);
    $documento = apPedir($proyectos, '500.00', $jefe);

    $firma = app(AprobacionesService::class)->autoaprobar('documento_prueba', $documento->id, 'aprobar', $jefe, 'Obra parada sin este material');

    $aviso = Notificacion::query()->where('type', AlertasDelNucleo::AUTOAPROBACION)->sole();
    $numero = app(RegistroDocumentos::class)->definicionDe('documento_prueba')?->identificador($documento->refresh());

    expect($aviso->notifiable_id)->toBe($gerente->id)
        ->and($aviso->clave_unica)->toBe("firma:{$firma->id}")
        ->and($aviso->data['title'])->toBe("{$jefe->name} se autoaprobó documento de prueba {$numero}")
        ->and($aviso->data['actions'][0]['url'])->toEndWith('/configuracion/aprobaciones?tab=autoaprobaciones');
});

test('autoaprobación: sobre el tope o sin tope en la regla, no; firma la de arriba', function (?string $tope) {
    $operaciones = apUnidad('Gerencia de Operaciones');
    $proyectos = apUnidad('Jefatura de Proyectos', $operaciones);
    apRegla('Su jefatura', ['documento'], tope: $tope);
    $jefe = apJefe($proyectos);
    Permission::findOrCreate(AprobacionesService::PERMISO_AUTOAPROBAR);
    $jefe->givePermissionTo(AprobacionesService::PERMISO_AUTOAPROBAR);
    $documento = apPedir($proyectos, '500.00', $jefe);

    expect(fn () => app(AprobacionesService::class)->autoaprobar('documento_prueba', $documento->id, 'aprobar', $jefe, 'Urgente'))
        ->toThrow(AprobacionRechazada::class, 'firma la jefatura de arriba');
})->with(['bajo el monto' => '400', 'sin tope' => null]);

test('observar: motivo de la lista y qué corregir; el documento vuelve a borrador y las firmas se anulan', function () {
    $operaciones = apUnidad('Gerencia de Operaciones');
    $proyectos = apUnidad('Jefatura de Proyectos', $operaciones);
    apMatriz($operaciones);
    $documento = apPedir($proyectos, '5000.00');
    apFirmar($documento, apJefe($proyectos));
    $servicio = app(AprobacionesService::class);

    expect(fn () => $servicio->observar('documento_prueba', $documento->id, 'aprobar', apJefe($operaciones), apObservacion()->id, ' '))
        ->toThrow(AprobacionRechazada::class, 'Escribe qué hay que corregir')
        ->and(fn () => $servicio->observar('documento_prueba', $documento->id, 'aprobar', apJefe($operaciones), Motivo::query()->firstOrCreate(['tipo_motivo_id' => TipoMotivo::query()->where('codigo', 'nucleo.prueba.anular')->sole()->id, 'codigo' => 'error'], ['nombre' => 'Error'])->id, 'x'))
        ->toThrow(AprobacionRechazada::class, 'Elige un motivo de la lista de observaciones');

    $servicio->observar('documento_prueba', $documento->id, 'aprobar', apJefe($operaciones), apObservacion()->id, 'Adjunta la segunda cotización');

    $ronda = RondaAprobacion::query()->sole();
    expect(apEstado($documento))->toBe('borrador')
        ->and($ronda->estado)->toBe(EstadoRondaAprobacion::Observada)
        ->and($ronda->firmas()->pluck('estado')->all())->toBe([EstadoFirmaAprobacion::Aprobada, EstadoFirmaAprobacion::Observada]);

    // Pedirla de nuevo empieza de cero: ronda nueva, con las dos firmas pendientes.
    app(TransicionesService::class)->transicionar($documento->refresh(), 'pedir_aprobacion', $documento->version, apJefe($proyectos)->givePermissionTo('nucleo.prueba.pedir_aprobacion'));
    $nueva = RondaAprobacion::query()->where('estado', EstadoRondaAprobacion::Abierta)->sole();
    expect($nueva->firmas()->pluck('estado')->unique()->all())->toBe([EstadoFirmaAprobacion::Pendiente]);
});

test('si el documento sale de «por aprobar» por otro camino, la ronda se anula con sus firmas pendientes', function () {
    $proyectos = apUnidad('Jefatura de Proyectos');
    apRegla('Su jefatura', ['documento']);
    $documento = apPedir($proyectos, '500.00');

    app(TransicionesService::class)->transicionarComoSistema($documento, 'devolver');

    $ronda = RondaAprobacion::query()->sole();
    expect($ronda->estado)->toBe(EstadoRondaAprobacion::Anulada)
        ->and($ronda->firmas()->sole()->estado)->toBe(EstadoFirmaAprobacion::Anulada)
        ->and(fn () => apFirmar($documento, apJefe($proyectos)))->toThrow(AprobacionRechazada::class, 'No hay una aprobación pendiente');
});

test('si la versión del documento cambió, las firmas ya no valen', function () {
    $proyectos = apUnidad('Jefatura de Proyectos');
    apRegla('Su jefatura', ['documento']);
    $documento = apPedir($proyectos, '500.00');
    RondaAprobacion::query()->update(['version_documento' => $documento->version - 1]);

    expect(fn () => apFirmar($documento, apJefe($proyectos)))
        ->toThrow(AprobacionRechazada::class, 'cambió desde que se pidió la aprobación');
});

test('una regla que pide «su jefatura» con un documento sin unidad no deja pedir la aprobación', function () {
    apRegla('Su jefatura', ['documento']);
    $quien = User::factory()->create()->givePermissionTo('nucleo.prueba.pedir_aprobacion');
    $documento = app(TransicionesService::class)->crear(new DocumentoPrueba(['nombre' => 'Sin unidad', 'monto' => '10']), $quien)->refresh();

    expect(fn () => app(TransicionesService::class)->transicionar($documento, 'pedir_aprobacion', $documento->version, $quien))
        ->toThrow(AprobacionRechazada::class, 'no tiene unidad')
        ->and(apEstado($documento))->toBe('borrador');
});

test('las reglas se validan al guardarlas', function (DatosRegla $datos, string $mensaje) {
    expect(fn () => app(AprobacionesService::class)->crearRegla($datos))->toThrow(AprobacionRechazada::class, $mensaje);
})->with([
    'paso que no requiere aprobación' => [new DatosRegla('X', 'documento_prueba', 'pedir_aprobacion', null, null, null, ['documento']), 'no es un paso'],
    'sin firmas' => [new DatosRegla('X', 'documento_prueba', 'aprobar', null, null, null, []), 'al menos una firma'],
    'hasta menor que más de' => [new DatosRegla('X', 'documento_prueba', 'aprobar', '1000', '500', null, ['documento']), 'mayor que'],
    'paso mal escrito' => [new DatosRegla('X', 'documento_prueba', 'aprobar', null, null, null, ['gerencia']), 'Paso no válido'],
    'unidad que no existe' => [new DatosRegla('X', 'documento_prueba', 'aprobar', null, null, null, ['unidad:999999']), 'no existe'],
    'monto con signo' => [new DatosRegla('X', 'documento_prueba', 'aprobar', '-5', null, null, ['documento']), 'sin signo'],
]);

test('una regla desactivada ya no calza; editarla exige la versión que se leyó', function () {
    $proyectos = apUnidad('Jefatura de Proyectos');
    $id = apRegla('Su jefatura', ['documento']);
    $servicio = app(AprobacionesService::class);

    $servicio->activarRegla($id, 1, false);
    apPedir($proyectos, '500.00');

    expect(RondaAprobacion::query()->sole()->firmas()->count())->toBe(0)
        ->and(fn () => $servicio->editarRegla($id, new DatosRegla('Otra', 'documento_prueba', 'aprobar', null, null, null, ['documento']), 1))
        ->toThrow(VersionDesactualizada::class);
});

test('sin pantalla: crear la regla, firmar y observar son acciones registradas', function () {
    $operaciones = apUnidad('Gerencia de Operaciones');
    $proyectos = apUnidad('Jefatura de Proyectos', $operaciones);
    $ejecutor = app(EjecutorAcciones::class);
    $datosRegla = ['nombre' => 'Su jefatura', 'documento_type' => 'documento_prueba', 'transicion' => 'aprobar', 'pasos' => ['documento', "unidad:{$operaciones->id}"]];

    expect(fn () => $ejecutor->ejecutar('nucleo.aprobaciones.crear-regla', $datosRegla, User::factory()->create()))
        ->toThrow(AccionRechazada::class);

    Permission::findOrCreate('aprobaciones.configurar');
    $ejecutor->ejecutar('nucleo.aprobaciones.crear-regla', $datosRegla, User::factory()->create()->givePermissionTo('aprobaciones.configurar'));

    $documento = apPedir($proyectos, '500.00');
    $ejecutor->ejecutar('nucleo.aprobaciones.firmar', ['documento_type' => 'documento_prueba', 'documento_id' => $documento->id, 'transicion' => 'aprobar'], apJefe($proyectos));
    $ejecutor->ejecutar('nucleo.aprobaciones.observar', ['documento_type' => 'documento_prueba', 'documento_id' => $documento->id, 'transicion' => 'aprobar', 'motivo_id' => apObservacion()->id, 'comentario' => 'Falta el plano'], apJefe($operaciones));

    expect(apEstado($documento))->toBe('borrador');
});

test('sin pantalla: autoaprobar y revisar exigen su permiso; editar, desactivar, activar y recalcular, el de configurar', function () {
    $operaciones = apUnidad('Gerencia de Operaciones');
    $proyectos = apUnidad('Jefatura de Proyectos', $operaciones);
    $ejecutor = app(EjecutorAcciones::class);
    $id = apRegla('Su jefatura', ['documento'], tope: '600');
    $jefe = apJefe($proyectos);
    $documento = apPedir($proyectos, '500.00', $jefe);
    $delDocumento = ['documento_type' => 'documento_prueba', 'documento_id' => $documento->id, 'transicion' => 'aprobar'];
    foreach (['aprobaciones.autoaprobar', 'aprobaciones.revisar', 'aprobaciones.configurar'] as $permiso) {
        Permission::findOrCreate($permiso);
    }

    expect(fn () => $ejecutor->ejecutar('nucleo.aprobaciones.autoaprobar', [...$delDocumento, 'motivo' => 'Urgente'], $jefe))->toThrow(AccionRechazada::class);
    $firma = $ejecutor->ejecutar('nucleo.aprobaciones.autoaprobar', [...$delDocumento, 'motivo' => 'Urgente'], $jefe->givePermissionTo('aprobaciones.autoaprobar'));

    expect(fn () => $ejecutor->ejecutar('nucleo.aprobaciones.revisar-autoaprobacion', ['firma_id' => $firma->id], apJefe($operaciones)))->toThrow(AccionRechazada::class);
    $ejecutor->ejecutar('nucleo.aprobaciones.revisar-autoaprobacion', ['firma_id' => $firma->id], apJefe($operaciones)->givePermissionTo('aprobaciones.revisar'));
    expect($firma->refresh()->revisada_at)->not->toBeNull();

    $admin = User::factory()->create();
    $regla = ['regla_id' => $id, 'version' => 1, 'nombre' => 'Su jefatura', 'documento_type' => 'documento_prueba', 'transicion' => 'aprobar', 'pasos' => ['documento']];
    expect(fn () => $ejecutor->ejecutar('nucleo.aprobaciones.editar-regla', $regla, $admin))->toThrow(AccionRechazada::class);
    $admin->givePermissionTo('aprobaciones.configurar');
    $ejecutor->ejecutar('nucleo.aprobaciones.editar-regla', [...$regla, 'monto_hasta' => '2000'], $admin);
    $ejecutor->ejecutar('nucleo.aprobaciones.desactivar-regla', ['regla_id' => $id, 'version' => 2], $admin);
    $ejecutor->ejecutar('nucleo.aprobaciones.activar-regla', ['regla_id' => $id, 'version' => 3], $admin);

    expect(ReglaAprobacion::query()->find($id))
        ->monto_hasta->toBe('2000.00')
        ->es_activa->toBeTrue()
        ->version->toBe(4);
});

test('una ronda que nació sin reglas se recalcula cuando un administrador las agrega', function () {
    $proyectos = apUnidad('Jefatura de Proyectos');
    $documento = apPedir($proyectos, '500.00');
    expect(RondaAprobacion::query()->sole()->firmas()->count())->toBe(0);

    apRegla('Su jefatura', ['documento']);
    Permission::findOrCreate('aprobaciones.configurar');
    app(EjecutorAcciones::class)->ejecutar('nucleo.aprobaciones.recalcular', ['documento_type' => 'documento_prueba', 'documento_id' => $documento->id, 'transicion' => 'aprobar'], User::factory()->create()->givePermissionTo('aprobaciones.configurar'));

    expect(RondaAprobacion::query()->orderBy('id')->pluck('estado')->all())->toBe([EstadoRondaAprobacion::Anulada, EstadoRondaAprobacion::Abierta])
        ->and(RondaAprobacion::query()->where('estado', 'abierta')->sole()->solicitante_user_id)->toBe(RondaAprobacion::query()->orderBy('id')->first()->solicitante_user_id);

    apFirmar($documento, apJefe($proyectos));
    expect(apEstado($documento))->toBe('aprobado');
});

test('si el documento cambió, firmar no firma: la ronda empieza de cero y se puede volver a firmar', function () {
    $proyectos = apUnidad('Jefatura de Proyectos');
    apRegla('Su jefatura', ['documento']);
    $documento = apPedir($proyectos, '500.00');
    RondaAprobacion::query()->update(['version_documento' => $documento->version - 1]);

    expect(fn () => apFirmar($documento, apJefe($proyectos)))->toThrow(AprobacionRechazada::class, 'las firmas empezaron de cero');

    expect(RondaAprobacion::query()->orderBy('id')->pluck('estado')->all())->toBe([EstadoRondaAprobacion::Anulada, EstadoRondaAprobacion::Abierta]);

    apFirmar($documento, apJefe($proyectos));
    expect(apEstado($documento))->toBe('aprobado');
});

test('un documento que no declara su monto no puede pedir aprobación: falla en vez de aprobarse en el nivel más bajo', function () {
    apRedefinir(new DefinicionPrueba(transiciones: [
        new TransicionDefinida('pedir_aprobacion', 'Pedir aprobación', 'borrador', 'por_aprobar'),
        new TransicionDefinida('aprobar', 'Aprobar', 'por_aprobar', 'aprobado', requiereAprobacion: true),
    ], conMonto: false));

    expect(fn () => apPedir(apUnidad('Jefatura de Proyectos'), '500.00'))->toThrow(LogicException::class, 'no declara su monto');
});

test('en una cadena de aprobaciones, la ronda siguiente la pide la misma persona, no el último firmante', function () {
    apRedefinir(new DefinicionPrueba(transiciones: [
        new TransicionDefinida('pedir_aprobacion', 'Pedir aprobación', 'borrador', 'por_aprobar'),
        new TransicionDefinida('aprobar', 'Aprobar', 'por_aprobar', 'aprobado', requiereAprobacion: true),
        new TransicionDefinida('enviar', 'Enviar', 'aprobado', 'enviado', requiereAprobacion: true),
    ]));
    $operaciones = apUnidad('Gerencia de Operaciones');
    $proyectos = apUnidad('Jefatura de Proyectos', $operaciones);
    apRegla('Su jefatura', ['documento']);
    app(AprobacionesService::class)->crearRegla(new DatosRegla('Envío: su jefatura', 'documento_prueba', 'enviar', null, null, null, ['documento']));
    $quien = User::factory()->create();
    $documento = apPedir($proyectos, '500.00', $quien);

    apFirmar($documento, apJefe($proyectos));

    $siguiente = RondaAprobacion::query()->where('transicion_codigo', 'enviar')->sole();
    expect($siguiente->solicitante_user_id)->toBe($quien->id);

    // Y su jefatura firma también la segunda: no la excluye haber firmado la primera.
    app(AprobacionesService::class)->firmar('documento_prueba', $documento->id, 'enviar', apJefe($proyectos));
    expect(apEstado($documento))->toBe('enviado');
});

test('el monto en soles se redondea al céntimo, no se trunca', function () {
    apRegla('Su jefatura', ['documento']);
    app(TipoCambioService::class)->registrar('USD', CarbonImmutable::now('America/Lima'), '3.8000', '3.8755', OrigenTipoCambio::Manual);

    apPedir(apUnidad('Jefatura de Proyectos'), '1.00', datos: ['moneda' => 'USD']);

    expect(RondaAprobacion::query()->sole()->monto_soles)->toBe('3.88');
});

test('un tope de autoaprobación en cero se rechaza con un mensaje claro', function () {
    expect(fn () => apRegla('Su jefatura', ['documento'], tope: '0'))->toThrow(AprobacionRechazada::class, 'mayor que cero');
});

test('la base no deja que una autoaprobación la revise quien la firmó', function () {
    $proyectos = apUnidad('Jefatura de Proyectos');
    apRegla('Su jefatura', ['documento'], tope: '600');
    $jefe = apJefe($proyectos);
    Permission::findOrCreate(AprobacionesService::PERMISO_AUTOAPROBAR);
    $jefe->givePermissionTo(AprobacionesService::PERMISO_AUTOAPROBAR);
    $documento = apPedir($proyectos, '500.00', $jefe);
    $firma = app(AprobacionesService::class)->autoaprobar('documento_prueba', $documento->id, 'aprobar', $jefe, 'Urgente');

    expect(fn () => DB::transaction(fn () => FirmaAprobacion::query()->whereKey($firma->id)->update(['revisada_por_user_id' => $jefe->id, 'revisada_at' => now()])))
        ->toThrow(QueryException::class);
});
