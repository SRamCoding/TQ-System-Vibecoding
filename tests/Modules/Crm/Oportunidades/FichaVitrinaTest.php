<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\CuentasServiceProvider;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Oportunidades\Filament\Resources\Oportunidades\Pages\VerOportunidad;
use App\Modules\Crm\Oportunidades\Filament\ResumenDeLaFicha;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\CotizacionAceptada;
use App\Modules\Crm\Oportunidades\Services\CotizacionDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\VersionDeCotizacion;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Plan 2.21d, D10, D13 y D15: la ficha con la cabecera de foto y la línea de etapas, y el Resumen: recorrido de los
 * documentos (lo que falta, punteado), la compuerta, la negociación por versiones (el margen solo con permiso de costos),
 * la línea de tiempo y la atención. Empresas y personas inventadas.
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    OportunidadesDePrueba::sinExigirCotizacion();
    Filament::setCurrentPanel('app');
    test()->travelTo('2026-11-16 10:00');
    test()->ejecutivo = User::factory()->create(['name' => 'Valeria Ficha Inventada'])->givePermissionTo([CuentasService::PERMISO_ATENDER, OportunidadesServiceProvider::PERMISO_VER, CuentasServiceProvider::PERMISO_VER]);
    $cuenta = Cuenta::factory()->create(['ejecutivo_user_id' => test()->ejecutivo->id, 'party_id' => Party::factory()->juridica()->create(['razon_social' => 'Hospital Ficha Inventado S.A.C.'])->id]);
    test()->oportunidad = OportunidadesDePrueba::crear($cuenta->id, ['ejecutivo_user_id' => test()->ejecutivo->id, 'nombre' => 'Cámaras del pabellón inventado']);
});

/** Tres versiones de una cotización, la última con margen bajo (por debajo de su mínimo). */
function conVersionesFalsas(): void
{
    $v = fn (int $n, string $total, string $margen): VersionDeCotizacion => new VersionDeCotizacion(
        100 + $n, "COT-2026-0009 v{$n}", 'COT-2026-0009', $n, 'Emitida', $total, 'S/', CarbonImmutable::parse("2026-11-0{$n}"), $margen, '22.00', false, null, '/cotizaciones/'.(100 + $n),
    );

    app()->instance(CotizacionDeOportunidad::class, new class([$v(1, '50000.00', '28.00'), $v(2, '47000.00', '24.00'), $v(3, '45000.00', '19.50')]) implements CotizacionDeOportunidad
    {
        /** @param  list<VersionDeCotizacion>  $versiones */
        public function __construct(private array $versiones) {}

        public function aceptada(int $oportunidadId): ?CotizacionAceptada
        {
            return null;
        }

        public function versiones(int $oportunidadId): array
        {
            return $this->versiones;
        }
    });
}

test('la cabecera lleva el dibujo de su línea, el monto y la línea de etapas; el Resumen va primero', function () {
    test()->actingAs(test()->ejecutivo);
    $html = Livewire::test(VerOportunidad::class, ['record' => test()->oportunidad->id])->html();

    expect($html)->toContain('class="tq-op-hero"')->toContain('<linearGradient id="tq-dl-'.test()->oportunidad->id.'"')
        ->toContain('Cámaras del pabellón inventado')->toContain('aria-current="step"')
        ->and(preg_match_all('/class="fi-tabs-item-label">\s*([^<]+?)\s*</', $html, $pestanas))->toBeGreaterThan(1)
        ->and(array_slice($pestanas[1], 0, 2))->toBe(['Resumen', 'Datos']);
});

test('el Resumen: recorrido con lo que falta punteado, compuerta con sus requisitos y la próxima acción por venir', function () {
    test()->actingAs(test()->ejecutivo);
    $r = app(ResumenDeLaFicha::class)->resumen(test()->oportunidad->refresh(), test()->ejecutivo);

    expect(array_column($r['recorrido']['nodos'], 'tipo'))->toBe(['futuro', 'futuro', 'futuro', 'futuro'])
        ->and(array_column($r['recorrido']['nodos'], 'codigo'))->toContain('Llega con la copia al ganar')
        ->and($r['compuerta']['hacia'])->toBe('Calificada')
        ->and(array_column($r['compuerta']['requisitos'], 'texto'))->toContain('Necesidad confirmada');

    Livewire::test(VerOportunidad::class, ['record' => test()->oportunidad->id])
        ->assertSee('Recorrido de los documentos')->assertSee('¿Qué falta para avanzar?')->assertSeeHtml('data-proxima-accion')
        ->assertSee('Línea de tiempo')->assertSee('Creada en Prospecto');
});

test('la negociación: el total de cada versión en un gráfico; el margen y su aviso solo con permiso de costos', function () {
    conVersionesFalsas();
    $sinCostos = app(ResumenDeLaFicha::class)->resumen(test()->oportunidad->refresh(), test()->ejecutivo);
    $conCostos = app(ResumenDeLaFicha::class)->resumen(test()->oportunidad, User::factory()->create()->givePermissionTo(GrupoSensible::Costos->permiso()));

    expect($sinCostos['negociacion']['puntos'])->toHaveCount(3)
        ->and(array_column($sinCostos['negociacion']['filas'], 'margen'))->toBe([null, null, null])
        ->and(implode(' ', array_column($sinCostos['negociacion']['puntos'], 'etiqueta')))->not->toContain('margen')
        ->and(array_column($sinCostos['atencion'], 'titulo'))->not->toContain('Margen por debajo de 22.00 %')
        ->and(array_column($conCostos['negociacion']['filas'], 'margen'))->toBe(['19.50', '24.00', '28.00'])
        ->and($conCostos['negociacion']['filas'][0]['bajo'])->toBeTrue()
        ->and(array_column($conCostos['atencion'], 'titulo'))->toContain('Margen por debajo de 22.00 %')
        ->and(array_column($conCostos['tiempo'], 'texto'))->toContain('Se emitió COT-2026-0009 v3 por S/ 45,000.00');

    test()->actingAs(test()->ejecutivo);
    Livewire::test(VerOportunidad::class, ['record' => test()->oportunidad->id])
        ->assertSee('¿Cómo se fue negociando?')->assertSee('El margen lo ve solo quien tiene permiso de costos.')->assertDontSee('19.50 %');
});

test('«Atención»: atascada y cierre estimado vencido', function () {
    Oportunidad::query()->whereKey(test()->oportunidad->id)->update(['fecha_cierre_estimada' => '2026-10-01']);
    $titulos = array_column(app(ResumenDeLaFicha::class)->resumen(test()->oportunidad->refresh(), test()->ejecutivo)['atencion'], 'titulo');

    expect($titulos)->toContain('Cierre estimado vencido');
});

test('D13: el botón «Foto» lo ven su ejecutivo y Gerencia Comercial; otro no', function () {
    test()->actingAs(test()->ejecutivo);
    Livewire::test(VerOportunidad::class, ['record' => test()->oportunidad->id])->assertActionVisible('cambiarFoto');

    test()->actingAs(User::factory()->create()->givePermissionTo([OportunidadesServiceProvider::PERMISO_VER, CuentasServiceProvider::PERMISO_VER, 'crm.oportunidades.editar']));
    Livewire::test(VerOportunidad::class, ['record' => test()->oportunidad->id])->assertActionVisible('cambiarFoto');

    test()->actingAs(User::factory()->create()->givePermissionTo([OportunidadesServiceProvider::PERMISO_VER, CuentasServiceProvider::PERMISO_VER]));
    Livewire::test(VerOportunidad::class, ['record' => test()->oportunidad->id])->assertActionHidden('cambiarFoto');
});
