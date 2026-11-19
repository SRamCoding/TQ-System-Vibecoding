<?php

declare(strict_types=1);

use App\Modules\Erp\Proyectos\Estados\DefinicionProyecto;
use App\Modules\Erp\Proyectos\Filament\Portada\DibujoProyecto;
use App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\HistorialDeProyecto;
use App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\Pages\ListarProyectos;
use App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\Pages\VerProyecto;
use App\Modules\Erp\Proyectos\Filament\ResumenDeProyecto;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\ProyectosServiceProvider;
use App\Modules\Erp\Proyectos\Services\DatosProyecto;
use App\Modules\Erp\Proyectos\Services\FiltrosPortada;
use App\Modules\Erp\Proyectos\Services\PortadaProyectos;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\ParametrosService;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * Las pantallas de Proyectos (plan 2.10, D1 y D3): la portada de la lista, «Nuevo proyecto interno» en el panel
 * deslizante, la ficha con sus botones y su historial. Personas y proyectos inventados.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->travelTo('2026-11-11 10:00');
    $techquk = Party::factory()->juridica()->create(['razon_social' => 'Techquk S.A.C.']);
    app(ParametrosService::class)->cambiarDesdeTexto(EmpresaService::PARAMETRO, $techquk->numero_documento, 1);
});

function entrarEnProyectos(string ...$permisos): User
{
    $usuario = User::factory()->create(['name' => 'Rosa Prueba Vela'])->givePermissionTo([ProyectosServiceProvider::PERMISO_VER, ...$permisos]);
    test()->actingAs($usuario);

    return $usuario;
}

function proyectoEnPantalla(string $nombre, ?User $jefe = null): Proyecto
{
    $jefe ??= User::factory()->create(['name' => 'Tomás Jefe Prueba']);
    $creado = app(ProyectosService::class)->crear(
        new DatosProyecto($nombre, $jefe->id, CarbonImmutable::parse('2026-11-15'), CarbonImmutable::parse('2027-02-28')),
        User::factory()->create(),
    );

    return Proyecto::query()->findOrFail($creado->id);
}

// ——— Portada de la lista ———

test('sin ver proyectos no se entra; con el permiso, la lista abre y ofrece el alta solo a quien crea', function () {
    $this->actingAs(User::factory()->create())->get('/proyectos')->assertForbidden();

    entrarEnProyectos();
    $this->get('/proyectos')->assertOk();
    Livewire::withQueryParams([])->test(ListarProyectos::class)->assertActionHidden('nuevo');

    entrarEnProyectos('erp.proyectos.crear');
    Livewire::withQueryParams([])->test(ListarProyectos::class)->assertActionVisible('nuevo');
});

/** Los proyectos que muestra la portada, en su orden. */
function enLaPortada(Testable $portada): array
{
    preg_match_all('/data-proyecto="(\d+)"/', $portada->html(), $ids);

    return array_map(intval(...), $ids[1]);
}

test('plan 2.61: la portada son tarjetas; los filtros dicen cuántos hay en cada opción y filtran', function () {
    $yo = entrarEnProyectos();
    $mio = proyectoEnPantalla('Cableado de la sede inventada', $yo);
    $enCurso = proyectoEnPantalla('Cámaras del almacén inventado');
    app(ProyectosService::class)->iniciarEjecucion($enCurso->id);

    $portada = Livewire::withQueryParams([])->test(ListarProyectos::class);
    $opciones = app(PortadaProyectos::class)->opciones($portada->instance()->filtros(), $yo);

    expect(enLaPortada($portada))->toBe([$enCurso->id, $mio->id])
        ->and($opciones['estados']['en_preparacion']['cantidad'])->toBe(1)
        ->and($opciones['estados']['en_ejecucion']['cantidad'])->toBe(1)
        ->and($opciones['estados']['cerrado']['cantidad'])->toBe(0)
        ->and($opciones['jefes'][$yo->id]['cantidad'])->toBe(1);

    expect(enLaPortada(Livewire::withQueryParams([])->test(ListarProyectos::class)->set('estados', ['en_ejecucion'])))->toBe([$enCurso->id])
        ->and(enLaPortada(Livewire::withQueryParams([])->test(ListarProyectos::class)->set('mios', true)))->toBe([$mio->id])
        ->and(enLaPortada(Livewire::withQueryParams([])->test(ListarProyectos::class)->set('orden', 'nombre')))->toBe([$mio->id, $enCurso->id]);

    // Con un estado marcado, las cantidades de Estado no cambian (cuentan sin su propio grupo); las de Jefe, sí.
    $conEstado = app(PortadaProyectos::class)->opciones(new FiltrosPortada(estados: ['en_ejecucion']), $yo);
    expect($conEstado['estados']['en_preparacion']['cantidad'])->toBe(1)->and($conEstado['jefes'][$yo->id]['cantidad'])->toBe(0);
});

test('«Jefe inactivo» es una opción de Salud; el enlace del aviso la abre marcada', function () {
    entrarEnProyectos();
    $saliente = User::factory()->create(['name' => 'Julio Saliente Prueba']);
    $huerfano = proyectoEnPantalla('Proyecto sin jefe activo', $saliente);
    $saliente->forceFill(['es_activo' => false])->save();
    $otro = proyectoEnPantalla('Proyecto con jefe activo');

    Livewire::withQueryParams([])->test(ListarProyectos::class)->assertSee('Jefe inactivo')
        ->set('salud', ['jefe-inactivo'])->assertSee('Quitar todos los filtros')
        ->tap(fn ($p) => expect(enLaPortada($p))->toBe([$huerfano->id]))
        ->call('quitarFiltros')
        ->tap(fn ($p) => expect(enLaPortada($p))->toBe([$otro->id, $huerfano->id]));

    expect(enLaPortada(Livewire::withQueryParams(['aviso' => 'jefe-inactivo'])->test(ListarProyectos::class)))->toBe([$huerfano->id]);
});

test('el buscador encuentra por código, nombre o cliente; últimos cambios enlaza al proyecto', function () {
    entrarEnProyectos();
    $sede = proyectoEnPantalla('Cableado de la sede inventada');
    $almacen = proyectoEnPantalla('Cámaras del almacén inventado');

    expect(enLaPortada(Livewire::withQueryParams([])->test(ListarProyectos::class)->set('buscar', 'almacen')))->toBe([$almacen->id])
        ->and(enLaPortada(Livewire::withQueryParams([])->test(ListarProyectos::class)->set('buscar', $sede->codigo)))->toBe([$sede->id])
        ->and(enLaPortada(Livewire::withQueryParams([])->test(ListarProyectos::class)->set('buscar', 'Techquk')))->toHaveCount(2);

    Livewire::withQueryParams([])->test(ListarProyectos::class)->set('buscar', 'no existe')
        ->assertSee('Ningún proyecto cumple los filtros')
        ->assertSee('Últimos cambios')
        ->assertSee("{$sede->codigo} · Cableado de la sede inventada")
        ->assertSeeHtml('href="'.e(VerProyecto::getUrl(['record' => $sede->id])).'"');
});

test('sin foto, la tarjeta lleva el dibujo con la paleta fija del proyecto y el chip genérico (D12)', function () {
    entrarEnProyectos();
    $proyecto = proyectoEnPantalla('Antena del cerro inventado');
    [$claro, $oscuro] = DibujoProyecto::paleta($proyecto->id);

    Livewire::withQueryParams([])->test(ListarProyectos::class)
        ->assertSeeHtml('<linearGradient id="tq-dp-'.$proyecto->id.'"')
        ->assertSeeHtml('stop-color="'.$claro.'"')->assertSeeHtml('stop-color="'.$oscuro.'"')
        ->assertSeeHtml('<rect x="261" y="52" width="44" height="44" rx="7"/>');

    expect(DibujoProyecto::paleta(8))->toBe(DibujoProyecto::paleta(16))->and(DibujoProyecto::paleta(1))->not->toBe(DibujoProyecto::paleta(2));
});

test('«Nuevo proyecto interno» crea el proyecto por la acción y abre su ficha', function () {
    $yo = entrarEnProyectos('erp.proyectos.crear');

    Livewire::withQueryParams([])->test(ListarProyectos::class)
        ->callAction('nuevo', [
            'nombre' => 'Renovación de la red interna',
            'jefe_proyecto_user_id' => $yo->id,
            'fecha_inicio' => '2026-11-20',
            'fecha_fin_prevista' => '2026-12-20',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Proyecto creado.');

    $proyecto = Proyecto::query()->where('nombre', 'Renovación de la red interna')->sole();

    expect($proyecto->es_interno)->toBeTrue()
        ->and($proyecto->jefe_proyecto_user_id)->toBe($yo->id);
});

// ——— Ficha ———

test('la ficha muestra código, cliente, estado, jefe y fechas; «Anular» solo en preparación y con permiso', function () {
    $proyecto = proyectoEnPantalla('Cableado de la sede inventada');

    entrarEnProyectos();
    Livewire::test(VerProyecto::class, ['record' => $proyecto->id])
        ->assertSee($proyecto->codigo)
        ->assertSee('Interno')
        ->assertSee('En preparación')
        ->assertSee('Tomás Jefe Prueba')
        ->assertSee('15/11/2026')
        ->assertActionHidden('erpProyectosProyectoAnular');

    entrarEnProyectos('erp.proyectos.proyecto.anular', 'erp.proyectos.proyecto.cerrar');
    Livewire::test(VerProyecto::class, ['record' => $proyecto->id])
        ->assertActionVisible('erpProyectosProyectoAnular')
        ->assertActionHidden('erpProyectosProyectoCerrar');
});

test('cerrar desde la ficha pasa el proyecto en ejecución a cerrado', function () {
    $proyecto = proyectoEnPantalla('Cámaras del almacén inventado');
    app(ProyectosService::class)->iniciarEjecucion($proyecto->id);
    entrarEnProyectos('erp.proyectos.proyecto.cerrar');

    Livewire::test(VerProyecto::class, ['record' => $proyecto->id])
        ->callAction('erpProyectosProyectoCerrar')
        ->assertNotified('Guardado.');

    expect($proyecto->refresh()->estado->codigo)->toBe(DefinicionProyecto::CERRADO);
});

test('el historial cuenta la creación del proyecto', function () {
    $proyecto = proyectoEnPantalla('Cableado de la sede inventada');
    entrarEnProyectos();

    Livewire::test(HistorialDeProyecto::class, ['registroId' => $proyecto->id])
        ->assertSee('Proyecto')
        ->assertSee('Jefe de proyecto')
        ->assertSee('Tomás Jefe Prueba')
        ->assertSee('15/11/2026');
});

// ——— Ficha con tablero (plan 2.61, D5) ———

test('la ficha abre con la cabecera (dibujo sin foto) y el Resumen primero; la foto la sube solo el jefe', function () {
    $jefe = User::factory()->create(['name' => 'Tomás Jefe Prueba'])->givePermissionTo(ProyectosServiceProvider::PERMISO_VER);
    $proyecto = proyectoEnPantalla('Cableado de la sede inventada', $jefe);

    entrarEnProyectos();
    $html = Livewire::test(VerProyecto::class, ['record' => $proyecto->id])
        ->assertSeeHtml('class="tq-cabecera-proyecto"')
        ->assertSeeHtml('<linearGradient id="tq-dp-'.$proyecto->id.'"')
        ->assertActionHidden('cambiarFoto')
        ->html();

    expect(strpos($html, 'Resumen'))->toBeLessThan(strpos($html, 'Datos'));

    test()->actingAs($jefe);
    Livewire::test(VerProyecto::class, ['record' => $proyecto->id])
        ->assertActionVisible('cambiarFoto')->assertActionHasLabel('cambiarFoto', 'Subir foto');
});

test('el Resumen responde las cuatro preguntas; el plazo y la actividad para todos, sin dinero para quien no ve costos', function () {
    $proyecto = proyectoEnPantalla('Cámaras del almacén inventado');
    entrarEnProyectos();

    Livewire::test(ResumenDeProyecto::class, ['proyectoId' => $proyecto->id])
        ->assertSee('¿Cuánto estamos ganando?')->assertSee('¿Vamos a tiempo?')->assertSee('¿Cuánto nos han pagado?')->assertSee('¿Qué necesita atención hoy?')
        ->assertSee('Aparece cuando el proyecto tenga su árbol de partidas.')
        ->assertSee('Fin previsto')->assertSee('28/02/2027')
        ->assertSee('Actividad reciente');

    test()->actingAs(User::factory()->create());
    Livewire::test(ResumenDeProyecto::class, ['proyectoId' => $proyecto->id])->assertForbidden();
});
