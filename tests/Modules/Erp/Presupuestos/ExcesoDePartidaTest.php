<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Imputacion\DestinoPartida;
use App\Modules\Erp\Presupuestos\Services\AvisoDeSaldos;
use App\Modules\Nucleo\Aprobaciones\DatosRegla;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Enums\TipoUnidadOrganizativa;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Excepciones\AprobacionRechazada;
use App\Modules\Nucleo\Excepciones\ExcesoDePartidaRechazado;
use App\Modules\Nucleo\Excepciones\ImputacionRechazada;
use App\Modules\Nucleo\Excepciones\TipoCambioFaltante;
use App\Modules\Nucleo\Imputacion\EleccionImputacion;
use App\Modules\Nucleo\Imputacion\LineaDeCosto;
use App\Modules\Nucleo\Imputacion\TipoImputacion;
use App\Modules\Nucleo\Models\AlertaEmitida;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\FirmaAprobacion;
use App\Modules\Nucleo\Models\Imputacion;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\Notificacion;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\RondaAprobacion;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\AprobacionesService;
use App\Modules\Nucleo\Services\ExcesoDePartidaService;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Costuras\ArbolDePrueba;
use Tests\Modules\Erp\Presupuestos\Fixtures\AgregarLineaDePrueba;
use Tests\Modules\Erp\Presupuestos\Fixtures\ComprometerLineasDePrueba;
use Tests\Modules\Erp\Presupuestos\Fixtures\DefinicionOrdenDePrueba;
use Tests\Modules\Erp\Presupuestos\Fixtures\LineaOrdenDePrueba;
use Tests\Modules\Erp\Presupuestos\Fixtures\OrdenDePrueba;
use Tests\Modules\Erp\Presupuestos\Fixtures\ServicioOrdenDePrueba;

/*
 * El exceso de partida en un documento con costo (plan 2.2, D2 a D7; costura C12), con la orden de prueba: la línea y su
 * destino en una transacción, la revisión al enviar y al aprobar, la regla de aprobación con su condición y la alerta.
 * Árbol: PC-01 Cable 1,000.00 y PC-02 Cámaras 5,000.00. Unidades y personas inventadas.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    Queue::fake();
    OrdenDePrueba::crearTablas();
    $registro = app(RegistroDocumentos::class);
    $registro->efecto('erp.presupuestos.prueba.comprometer', ComprometerLineasDePrueba::class);
    $registro->registrar(new DefinicionOrdenDePrueba);
    app(SincronizadorEstados::class)->sincronizar();
    app(RegistroAcciones::class)->registrar(AgregarLineaDePrueba::class);
    Permission::findOrCreate('erp.presupuestos.orden_prueba.enviar');
    Permission::findOrCreate('erp.presupuestos.prueba.agregar-linea');

    test()->arbol = ArbolDePrueba::armar([['PC-01', 'Cable', '1000.00', null], ['PC-02', 'Cámaras', '5000.00', null]]);
    test()->unidad = epUnidad('Obras de prueba');
    test()->pide = User::factory()->create()->givePermissionTo('erp.presupuestos.orden_prueba.enviar');
    test()->actingAs(test()->pide);
});

function epUnidad(string $nombre): UnidadOrganizativa
{
    $unidad = UnidadOrganizativa::factory()->create(['nombre' => $nombre, 'tipo' => TipoUnidadOrganizativa::Gerencia]);
    $puesto = Puesto::factory()->create(['nombre' => "Jefe de {$nombre}", 'unidad_organizativa_id' => $unidad->id]);
    $unidad->update(['jefe_puesto_id' => $puesto->id]);
    User::factory()->create(['puesto_id' => $puesto->id]);

    return $unidad->refresh();
}

function epJefe(UnidadOrganizativa $unidad): User
{
    return User::query()->where('puesto_id', $unidad->jefe_puesto_id)->sole();
}

/** @param list<string> $pasos */
function epRegla(string $nombre, array $pasos, CondicionRegla $condicion = CondicionRegla::Ninguna): void
{
    app(AprobacionesService::class)->crearRegla(new DatosRegla($nombre, 'orden_prueba', 'aprobar', null, null, null, $pasos, $condicion));
}

function epMotivoExceso(): int
{
    return (int) Motivo::query()->whereHas('tipoMotivo', fn ($query) => $query->where('codigo', DestinoPartida::FAMILIA_EXCESO))->value('id');
}

function epOrden(): OrdenDePrueba
{
    return (new ServicioOrdenDePrueba)->crear(test()->unidad->id, test()->pide);
}

function epLinea(OrdenDePrueba $orden, string $partida, string $monto, bool $conMotivo = false, string $moneda = 'PEN', string $fecha = '2026-11-12'): LineaOrdenDePrueba
{
    $eleccion = new EleccionImputacion(TipoImputacion::Partida, test()->arbol->proyectoId, test()->arbol->partidas[$partida], motivoId: $conMotivo ? epMotivoExceso() : null, comentario: $conMotivo ? 'El cliente pidió más cable' : null);

    return (new ServicioOrdenDePrueba)->agregarLinea($orden->id, "Línea {$partida}", $monto, $moneda, CarbonImmutable::parse($fecha), $eleccion);
}

function epEnviar(OrdenDePrueba $orden): void
{
    (new ServicioOrdenDePrueba)->enviar($orden->id, test()->pide);
}

function epAprobar(OrdenDePrueba $orden, ?UnidadOrganizativa $unidad = null): void
{
    app(AprobacionesService::class)->firmar('orden_prueba', $orden->id, 'aprobar', epJefe($unidad ?? test()->unidad));
}

function epEstado(OrdenDePrueba $orden): string
{
    return Estado::query()->findOrFail($orden->refresh()->estado_id)->codigo;
}

function epExcede(LineaOrdenDePrueba $linea): bool
{
    return (bool) Imputacion::query()->where('origen_type', 'linea_orden_prueba')->where('origen_id', $linea->id)->value('excede_partida');
}

test('si el destino falla, la línea no queda guardada', function () {
    $orden = epOrden();

    // Sin tipo de cambio del dólar en esa fecha, destinar() falla después de crear la línea: se deshace todo.
    expect(fn () => epLinea($orden, 'PC-01', '100.00', moneda: 'USD', fecha: '2026-11-12'))->toThrow(TipoCambioFaltante::class, 'No hay tipo de cambio de USD')
        ->and(LineaOrdenDePrueba::query()->count())->toBe(0);
});

test('por la acción, el gasto directo con proyecto sin motivo ni comentario se rechaza y la línea no queda', function () {
    $orden = epOrden();
    $datos = ['orden_id' => $orden->id, 'descripcion' => 'Taxi', 'monto' => '30', 'moneda' => 'PEN', 'fecha' => '2026-11-12',
        ...(new EleccionImputacion(TipoImputacion::NoPresupuestado, test()->arbol->proyectoId))->comoDatos()];

    expect(fn () => app(EjecutorAcciones::class)->ejecutar('erp.presupuestos.prueba.agregar-linea', $datos, User::factory()->create()->givePermissionTo('erp.presupuestos.prueba.agregar-linea')))
        ->toThrow(ImputacionRechazada::class, 'lleva su motivo y un comentario')
        ->and(LineaOrdenDePrueba::query()->count())->toBe(0);
});

test('al enviar, dos líneas que juntas exceden su partida piden motivo; con motivo se marcan las dos', function () {
    epRegla('Orden: su jefatura', ['documento']);
    epRegla('Exceso: su jefatura', ['documento'], CondicionRegla::ExcesoDePartida);
    $sin = epOrden();
    epLinea($sin, 'PC-01', '600.00');
    epLinea($sin, 'PC-01', '600.00');

    expect(fn () => epEnviar($sin))->toThrow(ExcesoDePartidaRechazado::class, 'La partida PC-01 Cable se excedería en S/ 200.00')
        ->and(epEstado($sin))->toBe('borrador');

    $con = epOrden();
    $a = epLinea($con, 'PC-01', '600.00', conMotivo: true);
    $b = epLinea($con, 'PC-01', '600.00', conMotivo: true);
    epEnviar($con);

    expect(epEstado($con))->toBe('por_aprobar')
        ->and([epExcede($a), epExcede($b)])->toBe([true, true]);
});

test('al aprobar se revisa otra vez: la segunda orden que excede sin motivo se rechaza y queda por aprobar', function () {
    epRegla('Orden: su jefatura', ['documento']);
    $a = epOrden();
    epLinea($a, 'PC-01', '600.00');
    $b = epOrden();
    epLinea($b, 'PC-01', '600.00');
    epEnviar($a);
    epEnviar($b);

    epAprobar($a);

    expect(epEstado($a))->toBe('aprobado')
        ->and(fn () => epAprobar($b))->toThrow(ExcesoDePartidaRechazado::class, 'se excedería en S/ 200.00')
        ->and(epEstado($b))->toBe('por_aprobar')
        ->and(FirmaAprobacion::query()->whereHas('ronda', fn ($query) => $query->where('aprobable_id', $b->id))->where('estado', 'aprobada')->count())->toBe(0);
});

test('al desmarcar una línea que ya no excede se limpian su motivo y su comentario, y la bitácora guarda los anteriores', function () {
    epRegla('Orden: su jefatura', ['documento']);
    epRegla('Exceso: su jefatura', ['documento'], CondicionRegla::ExcesoDePartida);
    $orden = epOrden();
    $linea = epLinea($orden, 'PC-01', '1200.00', conMotivo: true);
    app(ExcesoDePartidaService::class)->alEnviar([new LineaDeCosto($linea, '1200.00')]);

    expect(epExcede($linea))->toBeTrue();

    app(ExcesoDePartidaService::class)->alEnviar([new LineaDeCosto($linea, '900.00')]);
    $imputacion = Imputacion::query()->where('origen_id', $linea->id)->where('origen_type', 'linea_orden_prueba')->sole();

    expect([$imputacion->excede_partida, $imputacion->motivo_id, $imputacion->comentario])->toBe([false, null, null])
        ->and(AuditoriaCambio::query()->where('auditable_type', 'imputacion')->where('auditable_id', $imputacion->id)->get()
            ->contains(fn (AuditoriaCambio $cambio): bool => ($cambio->valores_anteriores['comentario'] ?? null) === 'El cliente pidió más cable'))->toBeTrue();
});

test('la regla del exceso entra solo cuando alguna línea excede', function () {
    $proyectos = epUnidad('Jefatura de prueba');
    epRegla('Orden: su jefatura', ['documento']);
    epRegla('Exceso: jefatura de prueba', ["unidad:{$proyectos->id}"], CondicionRegla::ExcesoDePartida);
    $normal = epOrden();
    epLinea($normal, 'PC-02', '100.00');
    $excede = epOrden();
    epLinea($excede, 'PC-01', '1500.00', conMotivo: true);

    epEnviar($normal);
    epEnviar($excede);
    $firmas = fn (OrdenDePrueba $orden): int => FirmaAprobacion::query()->whereHas('ronda', fn ($query) => $query->where('aprobable_type', 'orden_prueba')->where('aprobable_id', $orden->id))->count();

    expect($firmas($normal))->toBe(1)
        ->and($firmas($excede))->toBe(2);
});

test('con reglas de monto y sin regla de exceso, el envío con exceso se rechaza y no se abre la ronda', function () {
    epRegla('Orden: su jefatura', ['documento']);
    $orden = epOrden();
    epLinea($orden, 'PC-01', '1500.00', conMotivo: true);

    expect(fn () => epEnviar($orden))->toThrow(AprobacionRechazada::class, 'no hay una regla de aprobación para el exceso')
        ->and(epEstado($orden))->toBe('borrador')
        ->and(RondaAprobacion::query()->where('aprobable_type', 'orden_prueba')->count())->toBe(0);
});

test('al aprobar una línea que deja la partida al 92 %, la alerta llega a su responsable', function () {
    epRegla('Orden: su jefatura', ['documento']);
    $orden = epOrden();
    epLinea($orden, 'PC-02', '4600.00');
    epEnviar($orden);
    epAprobar($orden);

    $aviso = Notificacion::query()->where('type', AvisoDeSaldos::ALERTA)->sole();

    expect((int) $aviso->notifiable_id)->toBe(test()->arbol->responsable->id)
        ->and($aviso->data['title'])->toContain('PC-02 Cámaras')->toContain('92.00 %');
});

test('de menos del 90 % a excedida de una vez sale solo la de excedida, y la del 90 % ya no sale después', function () {
    epRegla('Orden: su jefatura', ['documento']);
    epRegla('Exceso: su jefatura', ['documento'], CondicionRegla::ExcesoDePartida);
    $orden = epOrden();
    epLinea($orden, 'PC-02', '6000.00', conMotivo: true);
    epEnviar($orden);
    epAprobar($orden);

    $pc02 = test()->arbol->partidas['PC-02'];

    expect(Notificacion::query()->where('type', AvisoDeSaldos::ALERTA)->count())->toBe(1);
    $huella = substr(hash('sha256', '5000.00'), 0, 12);

    expect(AlertaEmitida::query()->where('clave', AvisoDeSaldos::ALERTA.":partida:{$pc02}|excedida|{$huella}")->exists())->toBeTrue()
        ->and(AlertaEmitida::query()->where('clave', AvisoDeSaldos::ALERTA.":partida:{$pc02}|umbral|{$huella}")->exists())->toBeTrue()
        ->and(AlertaEmitida::query()->where('clave', 'like', '%5000%')->exists())->toBeFalse();
});

test('un exceso que aparece recién al aprobar se rechaza aunque la línea traiga motivo: su ronda no tiene la regla del exceso', function () {
    epRegla('Orden: su jefatura', ['documento']);
    epRegla('Exceso: su jefatura', ['documento'], CondicionRegla::ExcesoDePartida);
    $a = epOrden();
    epLinea($a, 'PC-01', '600.00');
    $b = epOrden();
    $lineaB = epLinea($b, 'PC-01', '600.00', conMotivo: true);
    epEnviar($a);
    epEnviar($b);

    // Al enviar la B no excedía: su motivo escrito de antemano se limpió y su ronda salió sin la regla del exceso.
    expect(Imputacion::query()->where('origen_type', 'linea_orden_prueba')->where('origen_id', $lineaB->id)->value('motivo_id'))->toBeNull();

    epAprobar($a);

    expect(fn () => epAprobar($b))->toThrow(ExcesoDePartidaRechazado::class, 'se excedería en S/ 200.00')
        ->and(epEstado($b))->toBe('por_aprobar');
});

test('la alerta del 90 % sale una sola vez por cruce: una segunda aprobación al 95 % no la repite', function () {
    epRegla('Orden: su jefatura', ['documento']);
    $primera = epOrden();
    epLinea($primera, 'PC-02', '4600.00');
    epEnviar($primera);
    epAprobar($primera);
    $segunda = epOrden();
    epLinea($segunda, 'PC-02', '150.00');
    epEnviar($segunda);
    epAprobar($segunda);

    expect(Notificacion::query()->where('type', AvisoDeSaldos::ALERTA)->count())->toBe(1);
});

test('una regla de exceso de partida no admite tope de autoaprobación: ni la base ni el servicio', function () {
    $regla = fn (?string $tope) => new DatosRegla('Exceso con tope', 'orden_prueba', 'aprobar', null, null, $tope, ['documento'], CondicionRegla::ExcesoDePartida);

    expect(fn () => app(AprobacionesService::class)->crearRegla($regla('500')))->toThrow(AprobacionRechazada::class, 'no admite autoaprobación')
        ->and(fn () => DB::transaction(fn () => DB::table('reglas_aprobacion')->insert([
            'nombre' => 'Directa', 'documento_type' => 'orden_prueba', 'transicion_codigo' => 'aprobar', 'tope_autoaprobacion' => 500,
            'condicion' => 'exceso_partida', 'es_activa' => true, 'created_by' => test()->pide->id,
        ])))->toThrow(QueryException::class, 'reglas_aprobacion_condicion_sin_autoaprobacion_check');
});

test('quien pide no aprueba su propio exceso, aunque sea la jefatura que firma', function () {
    epRegla('Orden: su jefatura', ['documento']);
    epRegla('Exceso: su jefatura', ['documento'], CondicionRegla::ExcesoDePartida);
    $jefe = epJefe(test()->unidad)->givePermissionTo(['erp.presupuestos.orden_prueba.enviar', AprobacionesService::PERMISO_AUTOAPROBAR]);
    $orden = (new ServicioOrdenDePrueba)->crear(test()->unidad->id, $jefe);
    epLinea($orden, 'PC-01', '1500.00', conMotivo: true);
    (new ServicioOrdenDePrueba)->enviar($orden->id, $jefe);

    expect(fn () => app(AprobacionesService::class)->autoaprobar('orden_prueba', $orden->id, 'aprobar', $jefe, 'Urgente para la obra'))->toThrow(AprobacionRechazada::class)
        ->and(fn () => app(AprobacionesService::class)->firmar('orden_prueba', $orden->id, 'aprobar', $jefe))->toThrow(AprobacionRechazada::class)
        ->and(epEstado($orden))->toBe('por_aprobar');
});

test('quien pide no aprueba su propio exceso aunque la regla del monto, con la misma jefatura, le deje autoaprobar', function () {
    // Plan 2.6, D2: la misma jefatura firma una sola vez por las dos reglas; manda la que no admite autoaprobación.
    app(AprobacionesService::class)->crearRegla(new DatosRegla('Orden: su jefatura', 'orden_prueba', 'aprobar', null, null, '5000', ['documento']));
    epRegla('Exceso: su jefatura', ['documento'], CondicionRegla::ExcesoDePartida);
    $jefe = epJefe(test()->unidad)->givePermissionTo(['erp.presupuestos.orden_prueba.enviar', AprobacionesService::PERMISO_AUTOAPROBAR]);
    $orden = (new ServicioOrdenDePrueba)->crear(test()->unidad->id, $jefe);
    epLinea($orden, 'PC-01', '1500.00', conMotivo: true);
    (new ServicioOrdenDePrueba)->enviar($orden->id, $jefe);

    expect(fn () => app(AprobacionesService::class)->autoaprobar('orden_prueba', $orden->id, 'aprobar', $jefe, 'Urgente para la obra'))->toThrow(AprobacionRechazada::class, 'no permite aprobar lo que uno mismo pidió')
        ->and(epEstado($orden))->toBe('por_aprobar');
});
