<?php

declare(strict_types=1);

use App\Modules\Nucleo\Aprobaciones\DatosRegla;
use App\Modules\Nucleo\database\seeders\CatalogosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\CondicionRegla;
use App\Modules\Nucleo\Enums\TipoUnidadOrganizativa;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Estados\TransicionDefinida;
use App\Modules\Nucleo\Excepciones\AprobacionRechazada;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\RondaAprobacion;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\AprobacionesService;
use App\Modules\Nucleo\Services\SincronizadorEstados;
use App\Modules\Nucleo\Services\TransicionesService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;

/*
 * Plan 2.22, D14: «solo si el margen queda bajo el mínimo». El documento dice si cumple la condición
 * (`condicionesDeAprobacion`); entonces entran las reglas que la piden, y sin una no se abre la ronda. Sin
 * autoaprobación, como el exceso y la PC-99.
 */

beforeEach(function () {
    test()->seed([ParametrosSeeder::class, CatalogosSeeder::class]);
    DocumentoPrueba::crearTabla();
    Permission::findOrCreate('nucleo.prueba.pedir_aprobacion');
});

/**
 * @param  list<CondicionRegla>  $condiciones
 */
function cmRegistrar(array $condiciones): void
{
    app(RegistroDocumentos::class)->registrar(new DefinicionPrueba(transiciones: [
        new TransicionDefinida('pedir_aprobacion', 'Pedir aprobación', 'borrador', 'por_aprobar'),
        new TransicionDefinida('aprobar', 'Aprobar', 'por_aprobar', 'aprobado', requiereAprobacion: true),
    ], condiciones: $condiciones));
    app(SincronizadorEstados::class)->sincronizar();
}

/** Una unidad con su jefatura ocupada. */
function cmUnidad(string $nombre): UnidadOrganizativa
{
    $unidad = UnidadOrganizativa::factory()->create(['nombre' => $nombre, 'tipo' => TipoUnidadOrganizativa::Gerencia]);
    $puesto = Puesto::factory()->create(['nombre' => "Jefe de {$nombre}", 'unidad_organizativa_id' => $unidad->id]);
    $unidad->update(['jefe_puesto_id' => $puesto->id]);
    User::factory()->create(['puesto_id' => $puesto->id]);

    return $unidad->refresh();
}

function cmRegla(UnidadOrganizativa $unidad, CondicionRegla $condicion, ?string $tope = null): int
{
    return app(AprobacionesService::class)->crearRegla(new DatosRegla("Regla {$condicion->value}", 'documento_prueba', 'aprobar', null, null, $tope, ["unidad:{$unidad->id}"], $condicion))->id;
}

function cmPedir(): DocumentoPrueba
{
    $quien = User::factory()->create();
    $quien->givePermissionTo('nucleo.prueba.pedir_aprobacion');
    $documento = app(TransicionesService::class)->crear(new DocumentoPrueba(['nombre' => 'Cotización de cámaras', 'monto' => '60939.92']), $quien)->refresh();
    app(TransicionesService::class)->transicionar($documento, 'pedir_aprobacion', $documento->version, $quien);

    return $documento->refresh();
}

test('con el margen bajo el mínimo entra la regla de margen y firma quien ella dice', function () {
    cmRegistrar([CondicionRegla::MargenBajoElMinimo]);
    $comercial = cmUnidad('Gerencia Comercial');
    cmRegla($comercial, CondicionRegla::MargenBajoElMinimo);

    cmPedir();

    $ronda = RondaAprobacion::query()->sole();
    expect($ronda->firmas()->sole()->unidad_organizativa_id)->toBe($comercial->id);
});

test('si el documento no la cumple, la regla de margen no entra', function () {
    cmRegistrar([]);
    cmRegla(cmUnidad('Gerencia Comercial'), CondicionRegla::MargenBajoElMinimo);
    $general = cmUnidad('Gerencia General');
    cmRegla($general, CondicionRegla::Ninguna);

    cmPedir();

    expect(RondaAprobacion::query()->sole()->firmas()->sole()->unidad_organizativa_id)->toBe($general->id);
});

test('con el margen bajo el mínimo y sin su regla, no se abre la ronda y lo dice', function () {
    cmRegistrar([CondicionRegla::MargenBajoElMinimo]);
    cmRegla(cmUnidad('Gerencia General'), CondicionRegla::Ninguna);

    expect(fn () => cmPedir())->toThrow(AprobacionRechazada::class, 'No hay una regla de aprobación para «solo si el margen queda bajo el mínimo», y este documento la cumple');
});

test('la regla de margen no admite autoaprobación, ni en el servicio ni en la base', function () {
    cmRegistrar([CondicionRegla::MargenBajoElMinimo]);
    $comercial = cmUnidad('Gerencia Comercial');

    expect(fn () => cmRegla($comercial, CondicionRegla::MargenBajoElMinimo, tope: '500.00'))
        ->toThrow(AprobacionRechazada::class, 'El margen bajo el mínimo no admite autoaprobación');

    $id = cmRegla($comercial, CondicionRegla::MargenBajoElMinimo);
    expect(fn () => DB::transaction(fn () => DB::table('reglas_aprobacion')->where('id', $id)->update(['tope_autoaprobacion' => '500.00'])))
        ->toThrow(QueryException::class);
});
