<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\EventoAuditoria;
use App\Modules\Nucleo\Enums\TipoParametro;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Parametro;
use App\Modules\Nucleo\RegistroAcciones\TipoCampo;
use Carbon\CarbonImmutable;

test('cada tipo de parámetro va y vuelve entre texto y PHP sin perder nada', function (TipoParametro $tipo, mixed $valor, string $texto) {
    expect($tipo->aTexto($valor))->toBe($texto);

    $leido = $tipo->desdeTexto($texto);

    $valor instanceof CarbonImmutable
        ? expect($leido)->toBeInstanceOf(CarbonImmutable::class)->and($leido->toDateString())->toBe($valor->toDateString())
        : expect($leido)->toBe($valor);
})->with([
    'texto' => [TipoParametro::Texto, 'PEN', 'PEN'],
    'entero' => [TipoParametro::Entero, 30, '30'],
    'decimal exacto' => [TipoParametro::Decimal, '18.00', '18.00'],
    'booleano verdadero' => [TipoParametro::Booleano, true, 'true'],
    'booleano falso' => [TipoParametro::Booleano, false, 'false'],
    'fecha' => [TipoParametro::Fecha, CarbonImmutable::parse('2027-01-01'), '2027-01-01'],
]);

test('un valor de otro tipo no se convierte en silencio', function (TipoParametro $tipo, mixed $valor) {
    $tipo->aTexto($valor);
})->with([
    'float en decimal' => [TipoParametro::Decimal, 18.0],
    'texto en entero' => [TipoParametro::Entero, '30'],
    'texto en booleano' => [TipoParametro::Booleano, 'sí'],
])->throws(LogicException::class);

test('cada tipo de parámetro tiene el campo de acción de su mismo tipo', function (TipoParametro $tipo) {
    expect($tipo->campo('valor', 'Valor')->tipo)->toBe(TipoCampo::from($tipo->value));
})->with(TipoParametro::cases());

test('un parámetro nuevo empieza en la versión 1 y su bitácora no anota la versión', function () {
    $parametro = Parametro::factory()->entero(30)->create();
    expect($parametro->refresh()->version)->toBe(1);

    $parametro->forceFill(['valor' => '45', 'version' => 2])->save();

    $ultimo = AuditoriaCambio::query()
        ->where('auditable_type', $parametro->getMorphClass())
        ->where('auditable_id', $parametro->id)
        ->latest('id')
        ->firstOrFail();

    expect($ultimo->evento)->toBe(EventoAuditoria::Actualizado)
        ->and($ultimo->valores_anteriores)->toBe(['valor' => '30'])
        ->and($ultimo->valores_nuevos)->toBe(['valor' => '45']);
});
