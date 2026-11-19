<?php

declare(strict_types=1);

use App\Modules\Erp\Proyectos\Estados\DefinicionProyecto;
use App\Modules\Erp\Proyectos\Events\ProyectoCerrado;
use App\Modules\Erp\Proyectos\Events\ProyectoCreado;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Services\DatosProyecto;
use App\Modules\Erp\Proyectos\Services\ProyectoRechazado;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\CatalogosService;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/*
 * Lo que solo la versión real de C2 revisa (plan 2.1, D2–D4): otras tablas, la numeración, los eventos, las
 * acciones registradas y las reglas de la base. El contrato compartido está en ContratoProyectosTest.php.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->usuario = User::factory()->create();
    test()->actingAs(test()->usuario);
});

function prFichaTechquk(): Party
{
    $techquk = Party::factory()->juridica()->create(['razon_social' => 'Techquk S.A.C.']);
    app(ParametrosService::class)->cambiarDesdeTexto(EmpresaService::PARAMETRO, $techquk->numero_documento, 1);

    return $techquk;
}

function prDatos(?int $jefe = null, ?int $cliente = null): DatosProyecto
{
    return new DatosProyecto('Cableado de la sede inventada', $jefe ?? test()->usuario->id, CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2027-01-31'), $cliente);
}

test('sin la ficha de Techquk no se crea un proyecto interno', function () {
    expect(fn () => app(ProyectosService::class)->crear(prDatos(), test()->usuario))->toThrow(ProyectoRechazado::class, 'ficha de Techquk');
});

test('el jefe de proyecto es un usuario activo y el cliente existe', function () {
    prFichaTechquk();
    $inactivo = User::factory()->create(['es_activo' => false]);

    expect(fn () => app(ProyectosService::class)->crear(prDatos($inactivo->id), test()->usuario))->toThrow(ProyectoRechazado::class, 'usuario activo')
        ->and(fn () => app(ProyectosService::class)->crearDesdeContrato(18, prDatos(cliente: 999999)))->toThrow(ProyectoRechazado::class, 'cliente no existe');
});

test('los códigos son correlativos por año: PRY-2026-0001, PRY-2026-0002', function () {
    CarbonImmutable::setTestNow('2026-11-10 09:00:00');
    prFichaTechquk();
    $servicio = app(ProyectosService::class);

    expect([$servicio->crear(prDatos(), test()->usuario)->codigo, $servicio->crear(prDatos(), test()->usuario)->codigo])->toBe(['PRY-2026-0001', 'PRY-2026-0002']);
});

test('crear un proyecto avisa con ProyectoCreado; desde un contrato, lo crea el sistema', function () {
    prFichaTechquk();
    Event::fake([ProyectoCreado::class]);
    $cliente = Party::factory()->juridica()->create();

    $proyecto = app(ProyectosService::class)->crearDesdeContrato(18, prDatos(cliente: $cliente->id), test()->usuario);

    Event::assertDispatched(ProyectoCreado::class, fn (ProyectoCreado $e) => $e->proyectoId === $proyecto->id && $e->contratoId === 18 && $e->codigo === $proyecto->codigo);
    expect(Proyecto::query()->findOrFail($proyecto->id)->created_by)->not->toBe(test()->usuario->id);
});

test('cerrar un proyecto en ejecución avisa con ProyectoCerrado y deja de aceptar costos', function () {
    prFichaTechquk();
    Event::fake([ProyectoCerrado::class]);
    $servicio = app(ProyectosService::class);
    $resumen = $servicio->crear(prDatos(), test()->usuario);
    $servicio->iniciarEjecucion($resumen->id);
    $proyecto = Proyecto::query()->findOrFail($resumen->id);

    app(TransicionesService::class)->transicionar($proyecto, 'cerrar', $proyecto->version, User::factory()->create()->givePermissionTo('erp.proyectos.proyecto.cerrar'));

    Event::assertDispatched(ProyectoCerrado::class, fn (ProyectoCerrado $e) => $e->proyectoId === $resumen->id);
    expect($servicio->resumen($resumen->id)->aceptaCostos)->toBeFalse();
});

test('nadie pasa un proyecto a ejecución a mano: es del sistema', function () {
    prFichaTechquk();
    $resumen = app(ProyectosService::class)->crear(prDatos(), test()->usuario);
    $proyecto = Proyecto::query()->findOrFail($resumen->id);

    expect(fn () => app(TransicionesService::class)->transicionar($proyecto, 'iniciar_ejecucion', $proyecto->version, test()->usuario))->toThrow(TransicionRechazada::class);
});

test('un proyecto con costos imputados no se anula', function () {
    prFichaTechquk();
    $resumen = app(ProyectosService::class)->crear(prDatos(), test()->usuario);
    // Hoy ningún destino deja costos en un proyecto en preparación (D6); se simula una línea para probar la regla.
    DB::table('imputaciones')->insert([
        'origen_type' => 'prueba', 'origen_id' => 1, 'proyecto_id' => $resumen->id,
        'centro_costo_id' => DB::table('centros_costo')->where('codigo', 'ADM')->value('id'),
        'fecha' => '2026-11-12', 'moneda_id' => app(CatalogosService::class)->idPorCodigo(Catalogo::Monedas, 'PEN'), 'tipo_cambio' => 1,
        'created_by' => test()->usuario->id, 'created_at' => now(),
    ]);
    $proyecto = Proyecto::query()->findOrFail($resumen->id);
    $otro = (int) Motivo::query()->whereHas('tipoMotivo', fn ($query) => $query->where('codigo', 'erp.proyectos.proyecto.anular'))->value('id');
    $anula = User::factory()->create()->givePermissionTo('erp.proyectos.proyecto.anular');

    expect(fn () => app(TransicionesService::class)->transicionar($proyecto, 'anular', $proyecto->version, $anula, new MotivoElegido($otro, 'Se registró dos veces'), 'Se registró dos veces'))
        ->toThrow(TransicionRechazada::class, 'ya tiene costos imputados');
});

test('la acción registrada crea un proyecto interno con el permiso, y sin él se rechaza', function () {
    prFichaTechquk();
    $datos = ['nombre' => 'Mantenimiento interno', 'jefe_proyecto_user_id' => test()->usuario->id, 'fecha_inicio' => '2026-11-10', 'fecha_fin_prevista' => '2026-12-20'];

    expect(fn () => app(EjecutorAcciones::class)->ejecutar('erp.proyectos.crear-interno', $datos, test()->usuario))->toThrow(AccionRechazada::class);

    $resumen = app(EjecutorAcciones::class)->ejecutar('erp.proyectos.crear-interno', $datos, User::factory()->create()->givePermissionTo('erp.proyectos.crear'));

    expect($resumen->esInterno)->toBeTrue()
        ->and($resumen->estado)->toBe(DefinicionProyecto::EN_PREPARACION);
});

test('la base exige que un proyecto sea interno o tenga contrato, y fechas en orden', function () {
    $fila = fn (array $cambios) => DB::table('proyectos')->insert([
        'codigo' => 'PRY-2026-9'.fake()->unique()->numerify('###'), 'nombre' => 'Directo', 'party_id' => Party::factory()->juridica()->create()->id,
        'es_interno' => false, 'contrato_id' => null, 'jefe_proyecto_user_id' => test()->usuario->id,
        'fecha_inicio' => '2026-11-10', 'fecha_fin_prevista' => '2026-12-10',
        'moneda_id' => app(CatalogosService::class)->idPorCodigo(Catalogo::Monedas, 'PEN'),
        'estado_id' => DB::table('estados')->where('documento_type', 'proyecto')->where('codigo', 'en_preparacion')->value('id'),
        'created_by' => test()->usuario->id, ...$cambios,
    ]);

    expect(fn () => DB::transaction(fn () => $fila([])))->toThrow(QueryException::class, 'proyectos_contrato_check')
        ->and(fn () => DB::transaction(fn () => $fila(['es_interno' => true, 'fecha_fin_prevista' => '2026-11-01'])))->toThrow(QueryException::class, 'proyectos_fechas_check');
});
