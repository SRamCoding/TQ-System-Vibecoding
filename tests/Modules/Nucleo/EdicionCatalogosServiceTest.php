<?php

declare(strict_types=1);

use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\Enums\Catalogo;
use App\Modules\Nucleo\Enums\EventoAuditoria;
use App\Modules\Nucleo\Enums\OrigenUbigeo;
use App\Modules\Nucleo\Excepciones\ElementoCatalogoRechazado;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Cubso;
use App\Modules\Nucleo\Models\FormaPago;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\TipoCambio;
use App\Modules\Nucleo\Models\TipoDocumento;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Models\UnidadMedida;
use App\Modules\Nucleo\RegistroAcciones\Campo;
use App\Modules\Nucleo\Services\EdicionCatalogosService;
use Illuminate\Database\Eloquent\Model;

/*
 * Plan 1.2b: crear, editar, desactivar, activar y borrar elementos de catálogo, con bloqueo optimista,
 * bitácora, código inmutable (E8), campos sensibles aparte (E6) y borrado solo de lo nunca usado (E1).
 */

// La moneda local es un parámetro: el servicio la consulta al desactivar o borrar monedas.
beforeEach(fn () => test()->seed(ParametrosSeeder::class));

function edicion(): EdicionCatalogosService
{
    return app(EdicionCatalogosService::class);
}

/**
 * Último evento de bitácora de un elemento.
 */
function ultimaBitacora(Model $elemento): AuditoriaCambio
{
    return AuditoriaCambio::query()
        ->where('auditable_type', $elemento->getMorphClass())
        ->where('auditable_id', $elemento->getKey())
        ->latest('id')
        ->firstOrFail();
}

test('todo catálogo se crea, se edita, se desactiva, se reactiva y se borra', function (Catalogo $catalogo) {
    $modelo = $catalogo->modelo();
    $campos = array_map(fn (Campo $campo): string => $campo->nombre, $catalogo->campos());
    $muestra = $modelo::factory()->raw();
    $codigo = (string) $muestra['codigo'];

    edicion()->crear($catalogo, $codigo, array_intersect_key($muestra, array_flip($campos)));
    $elemento = $modelo::query()->where('codigo', $codigo)->firstOrFail();
    expect($elemento->estaActivo())->toBeTrue()
        ->and($elemento->getAttribute('version'))->toBe(1);

    $texto = array_values(array_diff($campos, $catalogo->camposSensibles()))[0];
    edicion()->editar($catalogo, $codigo, [$texto => 'Cambiado'], 1);
    edicion()->desactivar($catalogo, $codigo, 2);
    edicion()->activar($catalogo, $codigo, 3);
    $elemento->refresh();
    expect($elemento->getAttribute($texto))->toBe('Cambiado')
        ->and($elemento->estaActivo())->toBeTrue()
        ->and($elemento->getAttribute('version'))->toBe(4);

    edicion()->borrar($catalogo, $codigo, 4);
    expect($modelo::query()->where('codigo', $codigo)->exists())->toBeFalse()
        ->and(ultimaBitacora($elemento)->evento)->toBe(EventoAuditoria::Eliminado);
})->with(array_values(array_filter(Catalogo::cases(), fn (Catalogo $c): bool => ! $c->esCerrado())));

// Los cerrados (plan 2.20a, N2) no se crean ni se borran: CatalogosComercialesTest.

test('crear deja el elemento activo, en versión 1 y con bitácora', function () {
    edicion()->crear(Catalogo::FormasPago, 'CRED45', ['nombre' => 'Crédito 45 días', 'dias' => 45]);

    $forma = FormaPago::query()->where('codigo', 'CRED45')->firstOrFail();

    expect($forma->dias)->toBe(45)
        ->and($forma->es_activa)->toBeTrue()
        ->and($forma->version)->toBe(1)
        ->and(ultimaBitacora($forma)->evento)->toBe(EventoAuditoria::Creado);
});

test('un ubigeo agregado a mano queda como manual', function () {
    edicion()->crear(Catalogo::Ubigeos, '140199', ['departamento' => 'LAMBAYEQUE', 'provincia' => 'CHICLAYO', 'distrito' => 'NUEVO']);

    expect(Ubigeo::query()->where('codigo', '140199')->value('origen'))->toBe(OrigenUbigeo::Manual);
});

test('un código que ya existe se rechaza con un mensaje claro', function () {
    FormaPago::factory()->create(['codigo' => 'CONTADO']);

    expect(fn () => edicion()->crear(Catalogo::FormasPago, 'CONTADO', ['nombre' => 'Otra']))
        ->toThrow(ElementoCatalogoRechazado::class, 'Ya existe «CONTADO» en formas de pago');
});

test('crear no acepta columnas que no son campos del catálogo', function (string $campo) {
    expect(fn () => edicion()->crear(Catalogo::Ubigeos, '140199', ['departamento' => 'X', 'provincia' => 'Y', 'distrito' => 'Z', $campo => 'inei']))
        ->toThrow(ElementoCatalogoRechazado::class, "El campo «{$campo}»");
})->with(['codigo', 'origen', 'version', 'es_activo']);

test('editar cambia los campos generales, sube la versión y deja bitácora', function () {
    $forma = FormaPago::factory()->create(['codigo' => 'CRED30', 'nombre' => 'Crédito 30', 'dias' => 30]);

    edicion()->editar(Catalogo::FormasPago, 'CRED30', ['nombre' => 'Crédito a 30 días', 'dias' => 30], 1);

    $forma->refresh();
    $bitacora = ultimaBitacora($forma);
    expect($forma->nombre)->toBe('Crédito a 30 días')
        ->and($forma->version)->toBe(2)
        ->and($bitacora->valores_anteriores)->toBe(['nombre' => 'Crédito 30'])
        ->and($bitacora->valores_nuevos)->toBe(['nombre' => 'Crédito a 30 días']);
});

test('editar sin cambios no sube la versión', function () {
    FormaPago::factory()->create(['codigo' => 'CRED30', 'nombre' => 'Crédito 30']);

    edicion()->editar(Catalogo::FormasPago, 'CRED30', ['nombre' => 'Crédito 30'], 1);

    expect(FormaPago::query()->where('codigo', 'CRED30')->value('version'))->toBe(1);
});

test('si otra persona guardó antes, se avisa y no se pisa su cambio', function () {
    FormaPago::factory()->create(['codigo' => 'CRED30', 'nombre' => 'Crédito 30']);
    edicion()->editar(Catalogo::FormasPago, 'CRED30', ['nombre' => 'Lo de Ana'], 1);

    expect(fn () => edicion()->editar(Catalogo::FormasPago, 'CRED30', ['nombre' => 'Lo de Luis'], 1))
        ->toThrow(VersionDesactualizada::class)
        ->and(FormaPago::query()->where('codigo', 'CRED30')->value('nombre'))->toBe('Lo de Ana');
});

test('toda operación sobre un elemento existente exige la versión leída', function (string $operacion) {
    FormaPago::factory()->create(['codigo' => 'CRED30']);
    edicion()->editar(Catalogo::FormasPago, 'CRED30', ['nombre' => 'Ya cambiado'], 1);

    expect(fn () => edicion()->{$operacion}(Catalogo::FormasPago, 'CRED30', 1))->toThrow(VersionDesactualizada::class);
})->with(['desactivar', 'borrar']);

test('el código no se edita', function () {
    FormaPago::factory()->create(['codigo' => 'CRED30']);

    expect(fn () => edicion()->editar(Catalogo::FormasPago, 'CRED30', ['codigo' => 'CRED31'], 1))
        ->toThrow(ElementoCatalogoRechazado::class, 'El campo «codigo»');
});

test('lo que mueve dinero no se cambia por editar, solo por editarSensibles', function () {
    $factura = TipoDocumento::factory()->create(['codigo' => '01', 'signo' => 1, 'afecta_cuenta_corriente' => true]);

    expect(fn () => edicion()->editar(Catalogo::TiposDocumento, '01', ['signo' => -1], 1))
        ->toThrow(ElementoCatalogoRechazado::class, 'El campo «signo»');

    edicion()->editarSensibles(Catalogo::TiposDocumento, '01', ['signo' => -1, 'afecta_cuenta_corriente' => false], 1);

    $factura->refresh();
    expect($factura->signo)->toBe(-1)
        ->and($factura->afecta_cuenta_corriente)->toBeFalse()
        ->and($factura->version)->toBe(2);
});

test('editarSensibles no cambia los campos generales', function () {
    Moneda::factory()->create(['codigo' => 'EUR']);

    expect(fn () => edicion()->editarSensibles(Catalogo::Monedas, 'EUR', ['nombre' => 'Euro'], 1))
        ->toThrow(ElementoCatalogoRechazado::class, 'El campo «nombre»');
});

test('un código que no está en el catálogo se rechaza', function () {
    expect(fn () => edicion()->editar(Catalogo::FormasPago, 'NOEXISTE', ['nombre' => 'X'], 1))
        ->toThrow(ElementoCatalogoRechazado::class, '«NOEXISTE» no está en formas de pago');
});

test('al elegir de otro catálogo, lo elegido debe estar activo', function () {
    $inactiva = UnidadMedida::factory()->create(['es_activa' => false]);
    $activa = UnidadMedida::factory()->create();

    expect(fn () => edicion()->crear(Catalogo::Cubso, '43211500', ['descripcion' => 'Computadoras', 'unidad_medida_id' => $inactiva->id]))
        ->toThrow(ElementoCatalogoRechazado::class, 'Lo elegido en «Unidad de medida»');

    edicion()->crear(Catalogo::Cubso, '43211500', ['descripcion' => 'Computadoras', 'unidad_medida_id' => $activa->id]);
    expect(Cubso::query()->where('codigo', '43211500')->value('unidad_medida_id'))->toBe($activa->id);
});

test('editar otro campo conserva una referencia que se desactivó después', function () {
    $unidad = UnidadMedida::factory()->create();
    Cubso::factory()->create(['codigo' => '43211500', 'unidad_medida_id' => $unidad->id]);
    $unidad->update(['es_activa' => false]);

    edicion()->editar(Catalogo::Cubso, '43211500', ['descripcion' => 'Computadoras portátiles', 'unidad_medida_id' => $unidad->id], 1);

    expect(Cubso::query()->where('codigo', '43211500')->value('descripcion'))->toBe('Computadoras portátiles');
});

test('desactivar lo que ya está desactivado se rechaza', function () {
    FormaPago::factory()->create(['codigo' => 'CRED30', 'es_activa' => false]);

    expect(fn () => edicion()->desactivar(Catalogo::FormasPago, 'CRED30', 1))
        ->toThrow(ElementoCatalogoRechazado::class, 'ya está desactivado');
});

test('lo que ya se usó no se borra: se sugiere desactivarlo y sigue igual', function () {
    $euro = Moneda::factory()->create(['codigo' => 'EUR']);
    TipoCambio::factory()->create(['moneda_id' => $euro->id]);

    expect(fn () => edicion()->borrar(Catalogo::Monedas, 'EUR', 1))
        ->toThrow(ElementoCatalogoRechazado::class, 'ya se usó en otros registros y no se puede borrar. Desactívalo')
        ->and(Moneda::query()->where('codigo', 'EUR')->exists())->toBeTrue()
        ->and(ultimaBitacora($euro)->evento)->toBe(EventoAuditoria::Creado);
});

test('la moneda local no se borra ni se desactiva', function (string $operacion) {
    Moneda::factory()->create(['codigo' => 'PEN']);

    expect(fn () => edicion()->{$operacion}(Catalogo::Monedas, 'PEN', 1))
        ->toThrow(ElementoCatalogoRechazado::class, '«PEN» es la moneda local del sistema')
        ->and(Moneda::query()->where('codigo', 'PEN')->value('es_activa'))->toBeTrue();
})->with(['desactivar', 'borrar']);
