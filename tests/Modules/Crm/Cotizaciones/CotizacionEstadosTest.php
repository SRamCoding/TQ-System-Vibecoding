<?php

declare(strict_types=1);

use App\Modules\Crm\Catalogo\Models\Producto;
use App\Modules\Crm\Cotizaciones\Enums\OrigenLinea;
use App\Modules\Crm\Cotizaciones\Enums\TipoRespaldo;
use App\Modules\Crm\Cotizaciones\Estados\DefinicionCotizacion;
use App\Modules\Crm\Cotizaciones\Excepciones\CotizacionRechazada;
use App\Modules\Crm\Cotizaciones\Services\DatosCotizacion;
use App\Modules\Crm\Cotizaciones\Services\DatosLinea;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Excepciones\EdicionRechazada;
use App\Modules\Nucleo\Excepciones\TransicionRechazada;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Permission;
use Tests\Modules\Crm\Cotizaciones\Fixtures\CotizacionesDePrueba;

/*
 * La cotización como documento (plan 2.22, paso 5): crear sobre la oportunidad, armar el borrador, emitir (congela),
 * aceptar con respaldo, rechazar, anular y anular la aceptación (D4, D5, D6, D9, D10, D21).
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    test()->travelTo(CarbonImmutable::parse('2026-11-12 10:00', 'America/Lima'));
    test()->cot = CotizacionesDePrueba::nueva();
});

function ceAceptar(int $id, int $version, string $fecha = '2026-11-12'): mixed
{
    return test()->cot->servicio()->aceptar($id, $version, CarbonImmutable::parse($fecha), TipoRespaldo::OrdenCompra, ' OC-2026-0099 ', null, null, test()->cot->ejecutivo);
}

test('se crea sobre la oportunidad, numerada COT, con la cuenta, la moneda de la oportunidad y 15 días de validez', function () {
    $c = test()->cot->crear();

    expect($c->numero)->toBe('COT-2026-0001')
        ->and([$c->numero_version, $c->cuenta_id, $c->oportunidad_id, $c->dias_validez, $c->estado->codigo])
        ->toBe([1, test()->cot->oportunidad->cuenta_id, test()->cot->oportunidad->id, 15, DefinicionCotizacion::BORRADOR])
        ->and(Moneda::query()->whereKey($c->moneda_id)->value('codigo'))->toBe('PEN')
        ->and($c->fecha_emision)->toBeNull();
});

test('no se cotiza sobre una oportunidad ajena sin permiso sobre todas, ni con un contacto de otra cuenta', function () {
    $otro = User::factory()->create();
    $otro->givePermissionTo(CotizacionesDePrueba::PERMISOS_EJECUTIVO);

    expect(fn () => test()->cot->servicio()->crear(test()->cot->oportunidad->id, new DatosCotizacion, $otro))
        ->toThrow(CotizacionRechazada::class, 'Solo el ejecutivo de la oportunidad')
        ->and(fn () => test()->cot->crear(new DatosCotizacion(contactoId: 999999)))->toThrow(CotizacionRechazada::class, 'Ese contacto no es de la cuenta.');

    $otro->givePermissionTo(Permission::findOrCreate('crm.cotizaciones.todas'));
    expect(test()->cot->servicio()->crear(test()->cot->oportunidad->id, new DatosCotizacion, $otro)->numero)->toBe('COT-2026-0001');
});

test('la línea del catálogo copia su descripción y unidad; la libre lleva las suyas; los totales se recalculan', function () {
    $producto = Producto::factory()->create(['descripcion' => 'Cámara IP domo 4 MP, lente 2.8 mm']);
    $c = test()->cot->crear();

    test()->cot->servicio()->agregarLinea($c->id, $c->version, new DatosLinea('2', '500.00', '300.00', '10', productoId: $producto->id), test()->cot->ejecutivo);
    $c = test()->cot->libre($c->refresh(), 'Instalación', '1', '200.00', '150.00');
    $catalogo = $c->lineas->first();

    expect([$catalogo->origen, $catalogo->descripcion, $catalogo->unidad_medida_id, $catalogo->subtotal, $catalogo->margen_porcentaje])
        ->toBe([OrigenLinea::Catalogo, 'Cámara IP domo 4 MP, lente 2.8 mm', $producto->unidad_medida_id, '900.00', '33.33'])
        ->and([$c->subtotal, $c->descuento_total, $c->igv, $c->total, $c->costo_total])->toBe(['1100.00', '100.00', '198.00', '1298.00', '750.00']);

    $producto->update(['es_activo' => false]);
    expect(fn () => test()->cot->servicio()->agregarLinea($c->id, $c->version, new DatosLinea('1', '1', '1', productoId: $producto->id), test()->cot->ejecutivo))
        ->toThrow(CotizacionRechazada::class, 'Ese producto no está activo en el catálogo.');
});

test('validaciones de la línea y del bloqueo optimista', function () {
    $c = test()->cot->crear();
    $agregar = fn (DatosLinea $d, ?int $version = null) => test()->cot->servicio()->agregarLinea($c->id, $version ?? $c->refresh()->version, $d, test()->cot->ejecutivo);

    expect(fn () => $agregar(new DatosLinea('0', '1', '1', descripcion: 'x', unidadMedidaId: CotizacionesDePrueba::unidad())))->toThrow(CotizacionRechazada::class, 'mayor que cero')
        ->and(fn () => $agregar(new DatosLinea('1', '1', '1', '101', descripcion: 'x', unidadMedidaId: CotizacionesDePrueba::unidad())))->toThrow(CotizacionRechazada::class, 'de 0 a 100 %')
        ->and(fn () => $agregar(new DatosLinea('1', '-1', '1', descripcion: 'x', unidadMedidaId: CotizacionesDePrueba::unidad())))->toThrow(CotizacionRechazada::class, 'sin signo')
        ->and(fn () => $agregar(new DatosLinea('1', '1', '1')))->toThrow(CotizacionRechazada::class, 'por catalogar lleva su descripción')
        ->and(fn () => $agregar(new DatosLinea('1', '1', '1', descripcion: 'x', unidadMedidaId: CotizacionesDePrueba::unidad()), 99))->toThrow(VersionDesactualizada::class);
});

test('emitir congela fecha, vencimiento (+15 días calendario), tipo de cambio e IGV; ya no se edita', function () {
    $c = test()->cot->emitida();

    expect($c->estado->codigo)->toBe(DefinicionCotizacion::EMITIDA)
        ->and([$c->fecha_emision?->toDateString(), $c->fecha_vencimiento?->toDateString(), $c->tipo_cambio, $c->igv_porcentaje])
        ->toBe(['2026-11-12', '2026-11-27', '3.7500', '18.00'])
        ->and(fn () => test()->cot->libre($c, 'Otra', '1', '1', '1'))->toThrow(CotizacionRechazada::class, 'está emitida: no se edita (los cambios van en una versión nueva).')
        ->and(fn () => $c->update(['total' => '1.00']))->toThrow(EdicionRechazada::class);
});

test('sin líneas no se emite', function () {
    $c = test()->cot->crear();

    expect(fn () => test()->cot->servicio()->emitir($c->id, $c->version, null, test()->cot->ejecutivo))->toThrow(TransicionRechazada::class, 'no tiene líneas');
});

test('aceptar pide fecha y respaldo; no futura, no antes de emitir ni después de vencer; queda una sola aceptada', function () {
    $c = test()->cot->emitida();

    expect(fn () => ceAceptar($c->id, $c->version, '2026-11-13'))->toThrow(CotizacionRechazada::class, 'no puede ser futura')
        ->and(fn () => ceAceptar($c->id, $c->version, '2026-11-11'))->toThrow(CotizacionRechazada::class, 'anterior a la emisión')
        ->and(fn () => test()->cot->servicio()->aceptar($c->id, $c->version, CarbonImmutable::parse('2026-11-12'), TipoRespaldo::Correo, '  ', null, null, test()->cot->ejecutivo))
        ->toThrow(CotizacionRechazada::class, 'referencia del documento de respaldo');

    $c = ceAceptar($c->id, $c->version);

    expect([$c->estado->codigo, $c->es_aceptada, $c->fecha_aceptacion?->toDateString(), $c->respaldo_tipo, $c->respaldo_referencia])
        ->toBe([DefinicionCotizacion::ACEPTADA, true, '2026-11-12', TipoRespaldo::OrdenCompra, 'OC-2026-0099']);

    $otra = test()->cot->emitida();
    expect(fn () => ceAceptar($otra->id, $otra->version))->toThrow(CotizacionRechazada::class, 'ya tiene otra cotización aceptada');
});

test('después de vencer no se acepta', function () {
    $c = test()->cot->emitida();
    test()->travelTo(CarbonImmutable::parse('2026-11-30 10:00', 'America/Lima'));

    expect(fn () => ceAceptar($c->id, $c->version, '2026-11-28'))->toThrow(CotizacionRechazada::class, 'venció el 27/11/2026: haz una versión nueva.');
});

test('el cliente la rechaza con un motivo de su lista; anular solo antes de emitir y con motivo', function () {
    $emitida = test()->cot->emitida();
    $motivo = new MotivoElegido(CotizacionesDePrueba::motivo(DefinicionCotizacion::CLAVE.'.rechazar', 'precio'));
    app(TransicionesService::class)->transicionar($emitida, DefinicionCotizacion::RECHAZAR, $emitida->version, test()->cot->ejecutivo, $motivo);

    $borrador = test()->cot->crear();
    $anular = new MotivoElegido(CotizacionesDePrueba::motivo(DefinicionCotizacion::CLAVE.'.anular', 'cotizacion_duplicada'));

    expect($emitida->refresh()->estado->codigo)->toBe(DefinicionCotizacion::RECHAZADA)
        ->and(fn () => app(TransicionesService::class)->transicionar($emitida, DefinicionCotizacion::ANULAR, $emitida->version, test()->cot->ejecutivo, $anular))->toThrow(TransicionRechazada::class);

    app(TransicionesService::class)->transicionar($borrador, DefinicionCotizacion::ANULAR, $borrador->version, test()->cot->ejecutivo, $anular);
    expect($borrador->refresh()->estado->codigo)->toBe(DefinicionCotizacion::ANULADA);
});

test('anular la aceptación es solo de quien tiene su permiso, con motivo, y la deja emitida sin respaldo', function () {
    $c = test()->cot->emitida();
    $c = ceAceptar($c->id, $c->version);
    $motivo = new MotivoElegido(CotizacionesDePrueba::motivo(DefinicionCotizacion::CLAVE.'.anular_aceptacion', 'aceptada_por_equivocacion'));

    expect(fn () => app(TransicionesService::class)->transicionar($c, DefinicionCotizacion::ANULAR_ACEPTACION, $c->version, test()->cot->ejecutivo, $motivo))->toThrow(TransicionRechazada::class);

    $gerencia = User::factory()->create();
    $gerencia->givePermissionTo(Permission::findOrCreate(DefinicionCotizacion::CLAVE.'.anular_aceptacion'));
    test()->cot->servicio()->anularAceptacion($c->id, $c->version, $motivo, null, $gerencia);

    expect([$c->refresh()->estado->codigo, $c->es_aceptada, $c->respaldo_tipo, $c->fecha_aceptacion])->toBe([DefinicionCotizacion::EMITIDA, false, null, null]);
});

test('las acciones registradas corren sin la interfaz', function () {
    $ejecutor = app(EjecutorAcciones::class);
    $c = $ejecutor->ejecutar('crm.cotizaciones.crear', ['oportunidad_id' => test()->cot->oportunidad->id, 'dias_validez' => 30], test()->cot->ejecutivo);
    $ejecutor->ejecutar('crm.cotizaciones.agregar-linea', ['cotizacion_id' => $c->id, 'version' => $c->refresh()->version, 'descripcion' => 'Instalación',
        'unidad_medida_id' => CotizacionesDePrueba::unidad(), 'cantidad' => '1', 'precio_unitario' => '1000', 'costo_unitario' => '500'], test()->cot->ejecutivo);
    $ejecutor->ejecutar(DefinicionCotizacion::CLAVE.'.emitir', ['cotizacion_id' => $c->id, 'version' => $c->refresh()->version], test()->cot->ejecutivo);
    $ejecutor->ejecutar(DefinicionCotizacion::CLAVE.'.aceptar', ['cotizacion_id' => $c->id, 'version' => $c->refresh()->version, 'fecha_aceptacion' => '2026-11-12',
        'respaldo_tipo' => 'buena_pro', 'respaldo_referencia' => 'Acta 12'], test()->cot->ejecutivo);

    expect([$c->refresh()->estado->codigo, $c->dias_validez, $c->fecha_vencimiento?->toDateString(), $c->total])->toBe([DefinicionCotizacion::ACEPTADA, 30, '2026-12-12', '1180.00']);
});

test('las líneas de una emitida no se cambian ni se borran, ni por el modelo (regla 6)', function () {
    $linea = test()->cot->emitida()->lineas->sole();

    expect(fn () => $linea->update(['precio_unitario' => '1.00']))->toThrow(EdicionRechazada::class)
        ->and(fn () => $linea->delete())->toThrow(EdicionRechazada::class);
});
