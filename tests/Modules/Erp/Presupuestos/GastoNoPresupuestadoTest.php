<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Events\PosibleAdicionalRegistrado;
use App\Modules\Erp\Presupuestos\Excepciones\ReclasificacionRechazada;
use App\Modules\Erp\Presupuestos\Imputacion\DestinoPartida;
use App\Modules\Erp\Presupuestos\Services\AvisoDeGastosNoPresupuestados;
use App\Modules\Erp\Presupuestos\Services\GastoPorMotivo;
use App\Modules\Erp\Presupuestos\Services\GastosNoPresupuestadosService;
use App\Modules\Erp\Presupuestos\Services\PartidasService;
use App\Modules\Erp\Presupuestos\Services\PosibleAdicional;
use App\Modules\Nucleo\Aprobaciones\DatosRegla;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Enums\TipoUnidadOrganizativa;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Excepciones\AprobacionRechazada;
use App\Modules\Nucleo\Excepciones\ImputacionRechazada;
use App\Modules\Nucleo\Imputacion\EleccionImputacion;
use App\Modules\Nucleo\Imputacion\FuenteDeDestinos;
use App\Modules\Nucleo\Imputacion\TipoImputacion;
use App\Modules\Nucleo\Models\CentroCosto;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\FirmaAprobacion;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\Notificacion;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\ReglaAprobacion;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use App\Modules\Nucleo\Services\AprobacionesService;
use App\Modules\Nucleo\Services\ImputacionService;
use App\Modules\Nucleo\Services\SeguridadService;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
 * El gasto no presupuestado (plan 2.6; flujo 7): una línea de la orden de prueba imputada a la PC-99 (D1), con su regla
 * de aprobación propia (D2). Árbol: PC-01 Cable 1,000.00 y la PC-99 que crea su aprobación. Unidades y personas
 * inventadas.
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

    test()->arbol = ArbolDePrueba::armar([['PC-01', 'Cable', '1000.00', null]]);
    test()->unidad = gnpUnidad('Obras de prueba');
    test()->pide = User::factory()->create()->givePermissionTo('erp.presupuestos.orden_prueba.enviar');
    test()->actingAs(test()->pide);
});

function gnpUnidad(string $nombre): UnidadOrganizativa
{
    $unidad = UnidadOrganizativa::factory()->create(['nombre' => $nombre, 'tipo' => TipoUnidadOrganizativa::Gerencia]);
    $puesto = Puesto::factory()->create(['nombre' => "Jefe de {$nombre}", 'unidad_organizativa_id' => $unidad->id]);
    $unidad->update(['jefe_puesto_id' => $puesto->id]);
    User::factory()->create(['puesto_id' => $puesto->id]);

    return $unidad->refresh();
}

function gnpJefe(UnidadOrganizativa $unidad): User
{
    return User::query()->where('puesto_id', $unidad->jefe_puesto_id)->sole();
}

/** @param list<string> $pasos */
function gnpRegla(string $nombre, array $pasos, CondicionRegla $condicion = CondicionRegla::Ninguna): void
{
    app(AprobacionesService::class)->crearRegla(new DatosRegla($nombre, 'orden_prueba', 'aprobar', null, null, null, $pasos, $condicion));
}

function gnpMotivo(string $nombre = 'Otro'): int
{
    return (int) Motivo::query()->where('nombre', $nombre)->whereHas('tipoMotivo', fn ($query) => $query->where('codigo', DestinoPartida::FAMILIA_NO_PRESUPUESTADO))->value('id');
}

function gnpOrden(): OrdenDePrueba
{
    return (new ServicioOrdenDePrueba)->crear(test()->unidad->id, test()->pide);
}

function gnpLineaPartida(OrdenDePrueba $orden, string $monto): LineaOrdenDePrueba
{
    $eleccion = new EleccionImputacion(TipoImputacion::Partida, test()->arbol->proyectoId, test()->arbol->partidas['PC-01']);

    return (new ServicioOrdenDePrueba)->agregarLinea($orden->id, 'Cable', $monto, 'PEN', CarbonImmutable::parse('2026-11-12'), $eleccion);
}

function gnpLineaPc99(OrdenDePrueba $orden, string $descripcion, string $monto, string $motivo = 'Otro', ?string $comentario = 'Sin movilidad de la empresa'): LineaOrdenDePrueba
{
    $eleccion = new EleccionImputacion(TipoImputacion::NoPresupuestado, test()->arbol->proyectoId, motivoId: gnpMotivo($motivo), comentario: $comentario);

    return (new ServicioOrdenDePrueba)->agregarLinea($orden->id, $descripcion, $monto, 'PEN', CarbonImmutable::parse('2026-11-12'), $eleccion);
}

function gnpEnviar(OrdenDePrueba $orden): void
{
    (new ServicioOrdenDePrueba)->enviar($orden->id, test()->pide);
}

function gnpAprobar(OrdenDePrueba $orden, UnidadOrganizativa ...$unidades): void
{
    foreach ($unidades === [] ? [test()->unidad] : $unidades as $unidad) {
        app(AprobacionesService::class)->firmar('orden_prueba', $orden->id, 'aprobar', gnpJefe($unidad));
    }
}

/** @return list<int> a quiénes llegó la alerta */
function gnpAvisados(string $alerta): array
{
    return Notificacion::query()->where('type', $alerta)->pluck('notifiable_id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
}

function gnpEstado(OrdenDePrueba $orden): string
{
    return Estado::query()->findOrFail($orden->refresh()->estado_id)->codigo;
}

/** @return list<string> la regla de cada firma, en orden */
function gnpFirmantes(OrdenDePrueba $orden): array
{
    return FirmaAprobacion::query()
        ->whereHas('ronda', fn ($query) => $query->where('aprobable_type', 'orden_prueba')->where('aprobable_id', $orden->id)->where('estado', 'abierta'))
        ->orderBy('orden')
        ->get()
        ->map(fn (FirmaAprobacion $firma): string => ReglaAprobacion::query()->whereKey($firma->regla_aprobacion_id)->value('nombre'))
        ->all();
}

test('la regla del gasto no presupuestado entra solo si alguna línea va a la PC-99', function () {
    $gerencia = gnpUnidad('Gerencia de prueba');
    gnpRegla('Orden: su jefatura', ['documento']);
    gnpRegla('Gasto no presupuestado: gerencia', ["unidad:{$gerencia->id}"], CondicionRegla::GastoNoPresupuestado);

    $normal = gnpOrden();
    gnpLineaPartida($normal, '100.00');
    gnpEnviar($normal);

    $conPc99 = gnpOrden();
    gnpLineaPartida($conPc99, '100.00');
    gnpLineaPc99($conPc99, 'Taxi', '45.00');
    gnpEnviar($conPc99);

    expect(gnpFirmantes($normal))->toBe(['Orden: su jefatura'])
        ->and(gnpFirmantes($conPc99))->toBe(['Orden: su jefatura', 'Gasto no presupuestado: gerencia']);
});

test('sin una regla activa para el gasto no presupuestado, la orden con una línea a la PC-99 no se envía', function () {
    gnpRegla('Orden: su jefatura', ['documento']);
    $orden = gnpOrden();
    gnpLineaPc99($orden, 'Taxi', '45.00');

    expect(fn () => gnpEnviar($orden))->toThrow(AprobacionRechazada::class, 'van a la PC-99')
        ->and(gnpEstado($orden))->toBe('borrador');
});

test('una regla del gasto no presupuestado no admite tope de autoaprobación: ni la base ni el servicio', function () {
    $regla = new DatosRegla('PC-99 con tope', 'orden_prueba', 'aprobar', null, null, '500', ['documento'], CondicionRegla::GastoNoPresupuestado);

    expect(fn () => app(AprobacionesService::class)->crearRegla($regla))->toThrow(AprobacionRechazada::class, 'El gasto no presupuestado no admite autoaprobación')
        ->and(fn () => DB::transaction(fn () => DB::table('reglas_aprobacion')->insert([
            'nombre' => 'Directa', 'documento_type' => 'orden_prueba', 'transicion_codigo' => 'aprobar', 'tope_autoaprobacion' => 500,
            'condicion' => 'gasto_no_presupuestado', 'es_activa' => true, 'created_by' => test()->pide->id,
        ])))->toThrow(QueryException::class, 'reglas_aprobacion_condicion_sin_autoaprobacion_check');
});

test('quien pide no aprueba su propio gasto no presupuestado, aunque la regla del monto le deje autoaprobar', function () {
    // La misma jefatura firma una sola vez por las dos reglas: manda la que no admite autoaprobación.
    app(AprobacionesService::class)->crearRegla(new DatosRegla('Orden: su jefatura', 'orden_prueba', 'aprobar', null, null, '1000', ['documento']));
    gnpRegla('Gasto no presupuestado: su jefatura', ['documento'], CondicionRegla::GastoNoPresupuestado);
    $jefe = gnpJefe(test()->unidad)->givePermissionTo(['erp.presupuestos.orden_prueba.enviar', AprobacionesService::PERMISO_AUTOAPROBAR]);
    $orden = (new ServicioOrdenDePrueba)->crear(test()->unidad->id, $jefe);
    gnpLineaPc99($orden, 'Taxi', '45.00');
    (new ServicioOrdenDePrueba)->enviar($orden->id, $jefe);

    expect(fn () => app(AprobacionesService::class)->autoaprobar('orden_prueba', $orden->id, 'aprobar', $jefe, 'Urgente para la obra'))->toThrow(AprobacionRechazada::class, 'no permite aprobar lo que uno mismo pidió')
        ->and(gnpEstado($orden))->toBe('por_aprobar');
});

test('la lista del gasto no presupuestado nace con los seis motivos del v4 y solo el cambio del cliente marca adicional', function () {
    $motivos = Motivo::query()->whereHas('tipoMotivo', fn ($query) => $query->where('codigo', DestinoPartida::FAMILIA_NO_PRESUPUESTADO))->orderBy('nombre')->get();

    expect($motivos->pluck('nombre')->all())->toBe(['Cambio pedido por el cliente', 'Error de metrado', 'Imprevisto en obra', 'Otro', 'Precio mayor al presupuestado', 'Reposición por pérdida o daño'])
        ->and($motivos->where('es_cambio_cliente', true)->pluck('nombre')->all())->toBe(['Cambio pedido por el cliente'])
        ->and(array_map(fn ($o): string => $o->etiqueta, app(FuenteDeDestinos::class)->motivosNoPresupuestados()))->toHaveCount(6);
});

test('por el selector, la línea va a la PC-99 del proyecto con su motivo, comentario y centro de referencia', function () {
    $centro = CentroCosto::factory()->create();
    $orden = gnpOrden();
    $eleccion = new EleccionImputacion(TipoImputacion::NoPresupuestado, test()->arbol->proyectoId, centroCostoId: $centro->id, motivoId: gnpMotivo('Imprevisto en obra'), comentario: 'Taxi a la obra');
    $linea = (new ServicioOrdenDePrueba)->agregarLinea($orden->id, 'Taxi', '45.00', 'PEN', CarbonImmutable::parse('2026-11-12'), $eleccion);
    $imputacion = app(ImputacionService::class)->de($linea);

    expect([$imputacion->partida_control_id, $imputacion->proyecto_id, $imputacion->centro_costo_id, $imputacion->motivo_id, $imputacion->comentario])
        ->toBe([test()->arbol->partidas['PC-99'], test()->arbol->proyectoId, $centro->id, gnpMotivo('Imprevisto en obra'), 'Taxi a la obra']);
});

test('la PC-99 no se elige como partida, ni con un motivo de excesos, ni en un proyecto sin árbol aprobado', function () {
    $fuente = app(FuenteDeDestinos::class);
    $excesos = (int) Motivo::query()->whereHas('tipoMotivo', fn ($query) => $query->where('codigo', DestinoPartida::FAMILIA_EXCESO))->value('id');
    $sinArbol = ArbolDePrueba::armar([['PC-01', 'Cable', '1000.00', null]], aprobar: false);

    expect(fn () => $fuente->destino(new EleccionImputacion(TipoImputacion::Partida, test()->arbol->proyectoId, test()->arbol->partidas['PC-99'], motivoId: gnpMotivo(), comentario: 'Taxi')))
        ->toThrow(ImputacionRechazada::class, 'La PC-99 se elige como «Gasto directo con proyecto»')
        ->and(fn () => $fuente->destino(new EleccionImputacion(TipoImputacion::NoPresupuestado, test()->arbol->proyectoId, motivoId: $excesos, comentario: 'Taxi')))
        ->toThrow(ImputacionRechazada::class, 'no es de la lista de gastos no presupuestados')
        ->and(fn () => $fuente->destino(new EleccionImputacion(TipoImputacion::NoPresupuestado, $sinArbol->proyectoId, motivoId: gnpMotivo(), comentario: 'Taxi')))
        ->toThrow(ImputacionRechazada::class, 'no hay PC-99');
});

test('al enviar, «Gasto no presupuestado registrado» llega al jefe del proyecto, una vez por línea y sin montos', function () {
    gnpRegla('Orden: su jefatura', ['documento']);
    gnpRegla('Gasto no presupuestado: su jefatura', ['documento'], CondicionRegla::GastoNoPresupuestado);
    $orden = gnpOrden();
    gnpLineaPartida($orden, '100.00');
    gnpLineaPc99($orden, 'Taxi', '45.00', 'Imprevisto en obra');
    gnpLineaPc99($orden, 'Taxi de vuelta', '30.00', 'Error de metrado');
    gnpEnviar($orden);
    $avisos = Notificacion::query()->where('type', AvisoDeGastosNoPresupuestados::ALERTA_REGISTRADO)->get();
    $aviso = $avisos->first(fn (Notificacion $n): bool => str_contains($n->data['title'], 'Imprevisto en obra'));

    expect($avisos)->toHaveCount(2)
        ->and($avisos->pluck('notifiable_id')->map(fn (mixed $id): int => (int) $id)->unique()->all())->toBe([test()->arbol->responsable->id])
        ->and($aviso->data['title'])->not->toContain('45')
        ->and(gnpAvisados(AvisoDeGastosNoPresupuestados::ALERTA_POSIBLE_ADICIONAL))->toBe([]);
});

test('si el envío se frena, no sale el aviso del gasto no presupuestado', function () {
    gnpRegla('Orden: su jefatura', ['documento']);
    $orden = gnpOrden();
    gnpLineaPc99($orden, 'Taxi', '45.00');

    expect(fn () => gnpEnviar($orden))->toThrow(AprobacionRechazada::class)
        ->and(gnpAvisados(AvisoDeGastosNoPresupuestados::ALERTA_REGISTRADO))->toBe([]);
});

test('al aprobar un cambio pedido por el cliente: «Posible adicional detectado» al jefe del proyecto y a Gerencia General, y el evento C11', function () {
    Event::fake([PosibleAdicionalRegistrado::class]);
    $gerente = User::factory()->create()->assignRole(SeguridadService::ROL_GERENCIA_GENERAL);
    gnpRegla('Orden: su jefatura', ['documento']);
    gnpRegla('Gasto no presupuestado: su jefatura', ['documento'], CondicionRegla::GastoNoPresupuestado);
    $orden = gnpOrden();
    $cambio = gnpLineaPc99($orden, 'Ducto adicional', '1286.00', 'Cambio pedido por el cliente');
    gnpLineaPc99($orden, 'Taxi', '45.00', 'Imprevisto en obra');
    gnpEnviar($orden);

    expect(gnpAvisados(AvisoDeGastosNoPresupuestados::ALERTA_POSIBLE_ADICIONAL))->toBe([]);

    gnpAprobar($orden);
    $imputacion = app(ImputacionService::class)->de($cambio);

    expect(gnpEstado($orden))->toBe('aprobado')
        ->and(gnpAvisados(AvisoDeGastosNoPresupuestados::ALERTA_POSIBLE_ADICIONAL))->toBe(collect([test()->arbol->responsable->id, $gerente->id])->sort()->values()->all());
    Event::assertDispatchedTimes(PosibleAdicionalRegistrado::class, 1);
    Event::assertDispatched(PosibleAdicionalRegistrado::class, fn (PosibleAdicionalRegistrado $e): bool => [$e->proyectoId, $e->imputacionId, $e->montoSoles] === [test()->arbol->proyectoId, $imputacion->id, '1286.00']);
});

test('la consulta da los gastos aprobados de la PC-99 por motivo y los posibles adicionales; lo no aprobado no cuenta', function () {
    gnpRegla('Orden: su jefatura', ['documento']);
    gnpRegla('Gasto no presupuestado: su jefatura', ['documento'], CondicionRegla::GastoNoPresupuestado);
    $aprobada = gnpOrden();
    gnpLineaPc99($aprobada, 'Taxi', '45.00', 'Imprevisto en obra');
    gnpLineaPc99($aprobada, 'Taxi de vuelta', '30.50', 'Imprevisto en obra');
    $cambio = gnpLineaPc99($aprobada, 'Ducto adicional', '1286.00', 'Cambio pedido por el cliente');
    gnpLineaPartida($aprobada, '100.00');
    gnpEnviar($aprobada);
    gnpAprobar($aprobada);
    $enviada = gnpOrden();
    gnpLineaPc99($enviada, 'Otro ducto', '500.00', 'Cambio pedido por el cliente');
    gnpEnviar($enviada);
    $consulta = app(GastosNoPresupuestadosService::class);

    expect(array_map(fn (GastoPorMotivo $g): array => [$g->motivo, $g->esCambioCliente, $g->cantidad, $g->totalSoles], $consulta->porMotivo(test()->arbol->proyectoId)))
        ->toBe([['Cambio pedido por el cliente', true, 1, '1286.00'], ['Imprevisto en obra', false, 2, '75.50']])
        ->and(array_map(fn (PosibleAdicional $p): array => [$p->imputacionId, $p->origenId, $p->comentario, $p->montoSoles], $consulta->posiblesAdicionales(test()->arbol->proyectoId)))
        ->toBe([[app(ImputacionService::class)->de($cambio)->id, $cambio->id, 'Sin movilidad de la empresa', '1286.00']])
        ->and($consulta->porMotivo(ArbolDePrueba::armar([['PC-01', 'Cable', '10.00', null]])->proyectoId))->toBe([]);
});

test('la acción de reclasificar está registrada con su permiso y sus campos, y rechaza hasta su tarea', function () {
    $accion = app(RegistroAcciones::class)->obtener('erp.presupuestos.reclasificar-gasto');
    $quien = User::factory()->create()->givePermissionTo(Permission::findOrCreate('erp.presupuestos.reclasificar-gasto'));
    $datos = ['imputacion_id' => 1, 'partida_destino_id' => 2, 'motivo_id' => 3, 'comentario' => 'Cabía en la partida de cable'];

    expect(array_map(fn ($campo): string => $campo->nombre, $accion->campos()))->toBe(['imputacion_id', 'partida_destino_id', 'motivo_id', 'comentario'])
        ->and($accion->permiso())->toBe('erp.presupuestos.reclasificar-gasto')
        ->and(fn () => app(EjecutorAcciones::class)->ejecutar('erp.presupuestos.reclasificar-gasto', $datos, $quien))
        ->toThrow(ReclasificacionRechazada::class, 'La reclasificación llega con su tarea.');
});

/*
 * Escenarios F7 del v4 (parte 8), con la orden de prueba en lugar de la rendición o la orden real (D1). El del margen
 * (1,372.00 → 1,327.00) queda para la tarea del margen (D4): aquí, el comprometido de la PC-99 que el margen leerá.
 */

test('F7: un taxi de 45.00 en la PC-99 sin comentario no se puede enviar: el comentario es obligatorio', function () {
    gnpRegla('Orden: su jefatura', ['documento']);
    gnpRegla('Gasto no presupuestado: su jefatura', ['documento'], CondicionRegla::GastoNoPresupuestado);
    $orden = gnpOrden();
    $datos = ['orden_id' => $orden->id, 'descripcion' => 'Taxi', 'monto' => '45.00', 'moneda' => 'PEN', 'fecha' => '2026-11-12',
        ...(new EleccionImputacion(TipoImputacion::NoPresupuestado, test()->arbol->proyectoId, motivoId: gnpMotivo('Imprevisto en obra')))->comoDatos()];
    $quien = User::factory()->create()->givePermissionTo(Permission::findOrCreate('erp.presupuestos.prueba.agregar-linea'));

    expect(fn () => app(EjecutorAcciones::class)->ejecutar('erp.presupuestos.prueba.agregar-linea', $datos, $quien))
        ->toThrow(ImputacionRechazada::class, 'Un gasto no presupuestado (PC-99) lleva su motivo y un comentario.')
        ->and(fn () => gnpLineaPc99($orden, 'Taxi', '45.00', 'Imprevisto en obra', comentario: '   '))
        ->toThrow(ImputacionRechazada::class, 'lleva su motivo y un comentario')
        ->and(LineaOrdenDePrueba::query()->count())->toBe(0);
});

test('F7: aprobado el taxi de 45.00 con motivo «imprevisto en obra», la PC-99 queda con 45.00 comprometido', function () {
    gnpRegla('Orden: su jefatura', ['documento']);
    gnpRegla('Gasto no presupuestado: su jefatura', ['documento'], CondicionRegla::GastoNoPresupuestado);
    $orden = gnpOrden();
    gnpLineaPc99($orden, 'Taxi', '45.00', 'Imprevisto en obra', comentario: 'Sin movilidad de la empresa para llevar el tablero');
    gnpEnviar($orden);
    gnpAprobar($orden);
    $pc99 = app(PartidasService::class)->saldo(test()->arbol->partidas['PC-99']);

    expect(gnpEstado($orden))->toBe('aprobado')
        ->and([$pc99->presupuestado, $pc99->comprometido])->toBe(['0.00', '45.00'])
        ->and(gnpAvisados(AvisoDeGastosNoPresupuestados::ALERTA_POSIBLE_ADICIONAL))->toBe([]);
});

test('F7: aprobado un gasto de 1,286.00 por «cambio pedido por el cliente», se marca posible adicional y avisa a quien cobra', function () {
    Event::fake([PosibleAdicionalRegistrado::class]);
    // Mientras no exista Administración de Contratos, Gerencia General (D6; docs/operaciones/antes-de-datos-reales.md).
    $gerente = User::factory()->create()->assignRole(SeguridadService::ROL_GERENCIA_GENERAL);
    gnpRegla('Orden: su jefatura', ['documento']);
    gnpRegla('Gasto no presupuestado: su jefatura', ['documento'], CondicionRegla::GastoNoPresupuestado);
    $orden = gnpOrden();
    $linea = gnpLineaPc99($orden, 'Ducto y cableado de la oficina nueva', '1286.00', 'Cambio pedido por el cliente', comentario: 'El cliente pidió un punto de red más');
    gnpEnviar($orden);
    gnpAprobar($orden);
    $adicionales = app(GastosNoPresupuestadosService::class)->posiblesAdicionales(test()->arbol->proyectoId);

    expect(array_map(fn (PosibleAdicional $p): array => [$p->origenId, $p->montoSoles], $adicionales))->toBe([[$linea->id, '1286.00']])
        ->and(app(PartidasService::class)->saldo(test()->arbol->partidas['PC-99'])->comprometido)->toBe('1286.00')
        ->and(gnpAvisados(AvisoDeGastosNoPresupuestados::ALERTA_POSIBLE_ADICIONAL))->toContain($gerente->id);
    Event::assertDispatched(PosibleAdicionalRegistrado::class, fn (PosibleAdicionalRegistrado $e): bool => $e->montoSoles === '1286.00');
});

test('si la regla sin autoaprobación se recorre primero, la firma compartida se queda con ella', function () {
    gnpRegla('Gasto no presupuestado: su jefatura', ['documento'], CondicionRegla::GastoNoPresupuestado);
    app(AprobacionesService::class)->crearRegla(new DatosRegla('Orden: su jefatura', 'orden_prueba', 'aprobar', null, null, '1000', ['documento']));
    $orden = gnpOrden();
    gnpLineaPc99($orden, 'Taxi', '45.00');
    gnpEnviar($orden);

    expect(gnpFirmantes($orden))->toBe(['Gasto no presupuestado: su jefatura']);
});
