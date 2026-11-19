<?php

declare(strict_types=1);

use App\Modules\Erp\Presupuestos\Imputacion\DestinoPartida;
use App\Modules\Erp\Presupuestos\PresupuestosServiceProvider;
use App\Modules\Erp\Presupuestos\Resumen\Soles;
use App\Modules\Erp\Presupuestos\Services\DineroDelProyecto;
use App\Modules\Erp\Presupuestos\Services\PartidasEnBase;
use App\Modules\Erp\Proyectos\Filament\Resources\Proyectos\Pages\ListarProyectos;
use App\Modules\Erp\Proyectos\Filament\ResumenDeProyecto;
use App\Modules\Erp\Proyectos\ProyectosServiceProvider;
use App\Modules\Erp\Proyectos\Resumen\ContenidoTarjeta;
use App\Modules\Erp\Proyectos\Resumen\LineaResumen;
use App\Modules\Erp\Proyectos\Resumen\RegistroTarjetasResumen;
use App\Modules\Erp\Proyectos\Resumen\SemaforoDeCosto;
use App\Modules\Erp\Proyectos\Services\DatosProyecto;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Erp\Proyectos\Services\VentaDelProyecto;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Imputacion\MomentoImputacion;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\ImputacionService;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Costuras\ArbolDePrueba;
use Tests\Modules\Nucleo\Fixtures\ModeloAuditado;

/*
 * Plan 2.61, D6, D10 y D11: lo del área 1 en el Resumen de la ficha. El escenario del flujo 1: 17,750.00 de costo
 * directo + 1,775.00 de gastos generales = 19,525.00 presupuestado. Personas inventadas.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    ModeloAuditado::crearTabla();
});

function comprometerEn(int $partidaId, string $monto, ?int $motivo = null): void
{
    $linea = ModeloAuditado::query()->create(['nombre' => 'Línea '.fake()->unique()->numerify('####')]);
    $imputaciones = app(ImputacionService::class);
    $imputaciones->destinar($linea, app(PartidasEnBase::class)->destino($partidaId, $motivo, $motivo === null ? null : 'Repuesto urgente inventado'), CarbonImmutable::parse('2026-11-12'), 'PEN');
    $imputaciones->mover($linea, MomentoImputacion::Comprometido, $monto);
}

function flujoUnoConCostos(): ArbolDePrueba
{
    $arbol = ArbolDePrueba::armar([['PC-01', 'Suministro e instalación', '17750.00', null], ['PC-02', 'Gastos generales', '1775.00', null]]);
    comprometerEn($arbol->partidas['PC-01'], '18500.00');
    $motivo = (int) Motivo::query()->whereHas('tipoMotivo', fn ($query) => $query->where('codigo', DestinoPartida::FAMILIA_NO_PRESUPUESTADO))->value('id');
    comprometerEn($arbol->partidas['PC-99'], '200.00', $motivo);

    return $arbol;
}

function quienVeCostos(): User
{
    return User::factory()->create()->givePermissionTo([PresupuestosServiceProvider::PERMISO_VER, GrupoSensible::Costos->permiso()]);
}

/** @return array<string, LineaResumen> */
function lineasDe(ContenidoTarjeta $tarjeta): array
{
    return array_column(array_map(fn (LineaResumen $l): array => [$l->etiqueta, $l], $tarjeta->lineas), 1, 0);
}

test('soles sin float: miles, decimales y negativos', function () {
    expect(Soles::texto('19525'))->toBe('S/ 19,525.00')->and(Soles::texto('1234567.5'))->toBe('S/ 1,234,567.50')
        ->and(Soles::texto('-750'))->toBe('−S/ 750.00')->and(Soles::texto('0'))->toBe('S/ 0.00');
});

test('flujo 1: 19,525.00 presupuestado; la proyección toma lo mayor por partida más la PC-99 y se pinta en ámbar', function () {
    $arbol = flujoUnoConCostos();
    $dinero = app(DineroDelProyecto::class)->de($arbol->proyectoId);

    expect($dinero)->presupuestado->toBe('19525.00')->comprometido->toBe('18700.00')->no_presupuestado->toBe('200.00')
        ->proyeccion->toBe('20475.00')->desvio->toBe('4.86')->tono->toBe('warning')
        ->venta->toBeNull()->margen_previsto->toBeNull();

    $secciones = app(RegistroTarjetasResumen::class)->paraProyecto($arbol->proyectoId, quienVeCostos());
    $lineas = lineasDe($secciones['dinero'][0]);

    expect($lineas['Presupuestado']->valor)->toBe('S/ 19,525.00')
        ->and($lineas['Proyección al cierre']->valor)->toBe('S/ 20,475.00')
        ->and($lineas['Proyección al cierre']->tono)->toBe('warning')
        ->and($lineas['Proyección al cierre']->nota)->toBe('+4.86 puntos sobre lo presupuestado')
        ->and($lineas['Gastos no presupuestados (PC-99)']->valor)->toBe('S/ 200.00')
        ->and(array_map(fn ($f) => $f->valor, $lineas['Comprometido']->desglose))->toBe(['S/ 18,500.00', 'S/ 200.00'])
        ->and(array_key_exists('Margen previsto', $lineas))->toBeFalse()
        // «Atención»: PC-01 excedida en 750.00.
        ->and($secciones['atencion'][0]->avisos[0]->texto)->toContain('PC-01 · Suministro e instalación: excedida en S/ 750.00');
});

test('los umbrales del semáforo son parámetros: rojo desde 6 puntos', function () {
    $dinero = app(DineroDelProyecto::class);

    expect($dinero->tono('2.00'))->toBe('success')->and($dinero->tono('2.01'))->toBe('warning')
        ->and($dinero->tono('6.00'))->toBe('warning')->and($dinero->tono('6.01'))->toBe('danger');
});

test('sin ver costos no aparece ninguna tarjeta con montos', function () {
    $arbol = flujoUnoConCostos();
    $sinCostos = User::factory()->create()->givePermissionTo(PresupuestosServiceProvider::PERMISO_VER);
    $secciones = app(RegistroTarjetasResumen::class)->paraProyecto($arbol->proyectoId, $sinCostos);

    expect($secciones['dinero'])->toBe([])
        ->and(array_map(fn (ContenidoTarjeta $t) => $t->titulo, $secciones['atencion']))->not->toContain('Partidas excedidas');
});

test('con la venta del contrato aparece el margen, sin tocar la ficha (D10)', function () {
    $arbol = flujoUnoConCostos();
    app()->instance(VentaDelProyecto::class, new class implements VentaDelProyecto
    {
        public function venta(int $proyectoId): ?string
        {
            return '25000.00';
        }
    });
    $lineas = lineasDe(app(RegistroTarjetasResumen::class)->paraProyecto($arbol->proyectoId, quienVeCostos())['dinero'][0]);

    expect($lineas['Vendido']->valor)->toBe('S/ 25,000.00')
        ->and($lineas['Margen previsto']->valor)->toBe('S/ 5,475.00')
        ->and($lineas['Margen proyectado']->valor)->toBe('S/ 4,525.00');
});

test('un proyecto sin árbol avisa que falta armarlo, también a quien no ve costos', function () {
    $arbol = ArbolDePrueba::armar([['PC-01', 'Suministro', '100.00', null]], aprobar: false);
    $otro = app(ProyectosService::class)->crear(
        new DatosProyecto('Sin árbol inventado', $arbol->responsable->id, CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2027-01-31')),
        $arbol->responsable,
    );
    $sinCostos = User::factory()->create()->givePermissionTo(PresupuestosServiceProvider::PERMISO_VER);

    expect(app(RegistroTarjetasResumen::class)->paraProyecto($otro->id, $sinCostos)['atencion'][0]->avisos[0]->texto)->toContain('Falta armar el árbol')
        ->and(app(DineroDelProyecto::class)->de($otro->id))->toBeNull();
});

test('portada: el semáforo de costo y los filtros de salud por costo, solo para quien ve costos', function () {
    Filament::setCurrentPanel('app');
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    $arbol = flujoUnoConCostos();
    $conCostos = quienVeCostos()->givePermissionTo(ProyectosServiceProvider::PERMISO_VER);
    $sinCostos = User::factory()->create()->givePermissionTo(ProyectosServiceProvider::PERMISO_VER);
    $portada = ListarProyectos::class;

    test()->actingAs($conCostos);
    Livewire::withQueryParams([])->test($portada)
        ->assertSeeHtml('data-semaforo="warning"')->assertSee('Con partidas excedidas')->assertSee('Costo en riesgo')
        ->set('salud', ['partidas-excedidas'])->assertSeeHtml('data-proyecto="'.$arbol->proyectoId.'"')
        ->set('salud', ['costo-en-riesgo'])->assertDontSeeHtml('data-proyecto="'.$arbol->proyectoId.'"');

    test()->actingAs($sinCostos);
    Livewire::withQueryParams(['salud' => ['partidas-excedidas']])->test($portada)
        ->assertDontSeeHtml('data-semaforo=')->assertDontSee('Con partidas excedidas')
        // Sin ver costos, el filtro de costo de la dirección no se aplica: el proyecto se ve igual.
        ->assertSeeHtml('data-proyecto="'.$arbol->proyectoId.'"');
});

test('en la ficha, cada monto con desglose se toca y abre el panel deslizante con sus partidas', function () {
    $arbol = flujoUnoConCostos();
    test()->actingAs(quienVeCostos()->givePermissionTo(ProyectosServiceProvider::PERMISO_VER));

    Livewire::test(ResumenDeProyecto::class, ['proyectoId' => $arbol->proyectoId])
        ->assertSee('S/ 19,525.00')->assertSee('S/ 20,475.00')->assertSee('+4.86 puntos sobre lo presupuestado')
        ->assertSeeHtml("\$dispatch('open-modal', { id: 'tq-desglose-dinero-0-1' })")
        ->assertSee('PC-01 · Suministro e instalación')
        ->assertSee('excedida en S/ 750.00');
});

test('D14: un árbol que no se aprobó no da dinero ni semáforo; el aprobado, sí', function () {
    $borrador = ArbolDePrueba::armar([['PC-01', 'Suministro', '500.00', null]], aprobar: false);
    $aprobado = flujoUnoConCostos();

    expect(app(DineroDelProyecto::class)->de($borrador->proyectoId))->toBeNull()
        ->and(app(SemaforoDeCosto::class)->tonos([$borrador->proyectoId, $aprobado->proyectoId], quienVeCostos()))
        ->toBe([$aprobado->proyectoId => 'warning'])
        // Sin el permiso de Presupuestos, ni semáforo.
        ->and(app(SemaforoDeCosto::class)->tonos([$aprobado->proyectoId], User::factory()->create()->givePermissionTo(GrupoSensible::Costos->permiso())))
        ->toBe([]);
});
