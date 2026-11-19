<?php

declare(strict_types=1);

use App\Modules\Crm\Cotizaciones\CotizacionesServiceProvider;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages\ListarCotizaciones;
use App\Modules\Crm\Cotizaciones\Filament\Resources\Cotizaciones\Pages\VerCotizacion;
use App\Modules\Crm\Cotizaciones\Models\Cotizacion;
use App\Modules\Crm\Cotizaciones\Services\DatosCotizacion;
use App\Modules\Crm\Cotizaciones\Services\PortadaCotizaciones;
use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Seguridad\GrupoSensible;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Crm\Cotizaciones\Fixtures\CotizacionesDePrueba;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * La portada de Cotizaciones (D19, con la convención de interfaz.md): pestañas con su cantidad, «Por vencer» con «Ver
 * cuáles», carpetas Cuenta › Oportunidad › Cotización › Versión con la más reciente primero, «Solo mías» y «Nueva
 * cotización» desde una oportunidad abierta que ya existe (D4).
 */

beforeEach(function () {
    config(['nucleo.ubigeos.csv' => '/no/existe.csv']);
    test()->seed(NucleoSeeder::class);
    Filament::setCurrentPanel('app');
    test()->cot = CotizacionesDePrueba::nueva();
    test()->cot->ejecutivo->givePermissionTo(Permission::findOrCreate(CotizacionesServiceProvider::PERMISO_VER));
});

test('la caja «Cotizaciones» está en el área CRM y la lista abre con su permiso, no sin él', function () {
    test()->actingAs(User::factory()->create())->get('/cotizaciones')->assertForbidden();
    test()->actingAs(test()->cot->ejecutivo)->get('/cotizaciones')->assertOk()
        ->assertSee('Carpetas')->assertSee('Solo mías');
    test()->actingAs(test()->cot->ejecutivo)->get('/')->assertSee('data-modulo="cotizaciones"', false);
});

test('pestañas con su cantidad; la tabla trae v2 antes que v1 y el margen solo con su permiso', function () {
    $v1 = test()->cot->emitida();
    $v2 = test()->cot->servicio()->nuevaVersion($v1->id, CotizacionesDePrueba::motivoVersion(), null, test()->cot->ejecutivo);
    test()->cot->crear();
    test()->actingAs(test()->cot->ejecutivo);

    Livewire::test(ListarCotizaciones::class)
        ->assertCanSeeTableRecords([$v2, $v1], inOrder: true)
        ->assertTableColumnHidden('margen_porcentaje')
        ->set('activeTab', 'en_curso')
        ->assertCanSeeTableRecords([$v2])
        ->assertCanNotSeeTableRecords([$v1])
        ->set('activeTab', 'emitidas')
        ->assertCanSeeTableRecords([$v1]);

    expect(app(PortadaCotizaciones::class)->enPestana(Cotizacion::query(), 'en_curso')->count())->toBe(2);

    test()->cot->ejecutivo->givePermissionTo(GrupoSensible::Margen->permiso());
    Livewire::test(ListarCotizaciones::class)->assertTableColumnVisible('margen_porcentaje');
});

test('«Por vencer»: avisa las emitidas que vencen dentro del parámetro, y «Ver cuáles» filtra la tabla', function () {
    $pronto = test()->cot->emitida();
    $lejos = test()->cot->emitida();
    $hoy = CarbonImmutable::parse(Calendario::hoy());
    $dias = app(ParametrosService::class)->entero(CotizacionesServiceProvider::PARAMETRO_DIAS_POR_VENCER);
    DB::table('cotizaciones')->where('id', $pronto->id)->update(['fecha_vencimiento' => $hoy->addDays($dias)->toDateString()]);
    DB::table('cotizaciones')->where('id', $lejos->id)->update(['fecha_vencimiento' => $hoy->addDays($dias + 1)->toDateString()]);
    test()->actingAs(test()->cot->ejecutivo);

    Livewire::test(ListarCotizaciones::class)
        ->assertSee('1 cotización emitida por vencer')
        ->call('verAviso', 'por-vencer')
        ->assertCanSeeTableRecords([$pronto])
        ->assertCanNotSeeTableRecords([$lejos])
        ->assertSee('Ver todas');
});

test('carpetas: cuenta › oportunidad › número › versiones (la más nueva primero) y la oportunidad con sus presupuestos', function () {
    $v1 = test()->cot->emitida();
    $v2 = test()->cot->servicio()->nuevaVersion($v1->id, CotizacionesDePrueba::motivoVersion(), null, test()->cot->ejecutivo);

    $arbol = app(PortadaCotizaciones::class)->carpetas(Cotizacion::query());
    $op = $arbol['cuentas'][0]['oportunidades'][0];

    expect($arbol['recortadas'])->toBeFalse()
        ->and($arbol['cuentas'])->toHaveCount(1)
        ->and($op['id'])->toBe(test()->cot->oportunidad->id)
        ->and($op['numeros'][0]['numero'])->toBe($v1->numero)
        ->and(array_column($op['numeros'][0]['versiones'], 'version'))->toBe([2, 1])
        ->and(array_column($op['numeros'][0]['versiones'], 'id'))->toBe([$v2->id, $v1->id])
        ->and($op['presupuestos'])->toBe([]);
});

test('elegir una carpeta recorta la tabla sin recargar; «Solo mías» deja las de mis oportunidades', function () {
    $mia = test()->cot->emitida();
    $otroEjecutivo = User::factory()->create();
    $ajena = OportunidadesDePrueba::crear(Cuenta::factory()->create()->id, ['ejecutivo_user_id' => $otroEjecutivo->id]);
    $deOtro = test()->cot->servicio()->crear($ajena->id, new DatosCotizacion, $otroEjecutivo->givePermissionTo(CotizacionesDePrueba::PERMISOS_EJECUTIVO));
    test()->actingAs(test()->cot->ejecutivo);

    Livewire::test(ListarCotizaciones::class)
        ->assertCanSeeTableRecords([$mia, $deOtro])
        ->call('verCarpeta', "oportunidad:{$ajena->id}")
        ->assertSet('carpeta', "oportunidad:{$ajena->id}")
        ->assertCanSeeTableRecords([$deOtro])
        ->assertCanNotSeeTableRecords([$mia])
        ->call('verCarpeta', 'cualquier cosa')
        ->assertSet('carpeta', null)
        ->call('cambiarSoloMias')
        ->assertCanSeeTableRecords([$mia])
        ->assertCanNotSeeTableRecords([$deOtro]);
});

test('«Nueva cotización» elige una oportunidad abierta que ya existe y abre la ficha creada', function () {
    test()->actingAs(test()->cot->ejecutivo);

    Livewire::test(ListarCotizaciones::class)
        ->callAction('nueva', ['oportunidad_id' => test()->cot->oportunidad->id])
        ->assertHasNoActionErrors()
        ->assertNotified('Cotización creada, en borrador.')
        ->assertRedirect(VerCotizacion::getUrl(['record' => Cotizacion::query()->sole()->id]));

    expect(Cotizacion::query()->sole()->oportunidad_id)->toBe(test()->cot->oportunidad->id);
});
