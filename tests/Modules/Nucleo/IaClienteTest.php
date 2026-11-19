<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\ResultadoLlamadaIa;
use App\Modules\Nucleo\Ia\ArchivoParaIa;
use App\Modules\Nucleo\Ia\ClienteAnthropic;
use App\Modules\Nucleo\Ia\ClienteModeloLenguajeFalso;
use App\Modules\Nucleo\Ia\ClientesModeloLenguaje;
use App\Modules\Nucleo\Ia\CostoLlamadaIa;
use App\Modules\Nucleo\Ia\PedidoModeloIa;
use App\Modules\Nucleo\Models\IaModelo;
use App\Modules\Nucleo\Models\IaPrecioModelo;
use App\Modules\Nucleo\Models\IaProveedor;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * El cliente de la Claude API (plan 1.11, D7) contra `Http::fake`, nunca contra el servicio real: la forma del pedido,
 * la lectura de la salida y de los tokens, y cada error. Además, las claves desde sus variables (D5) y el costo de una
 * llamada al precio vigente (D3). Modelos, claves y precios inventados.
 */

function iaPedido(): PedidoModeloIa
{
    return new PedidoModeloIa(
        modelo: 'modelo-de-prueba',
        maxTokensSalida: 2048,
        prompt: 'Propón el nombre comercial.',
        contexto: 'Razón social: Comercial Andina S.A.C.',
        archivos: [new ArchivoParaIa('application/pdf', '%PDF-1.4 prueba'), new ArchivoParaIa('image/png', 'png-de-prueba')],
        esquema: ['type' => 'object', 'properties' => ['nombre_comercial' => ['type' => 'string']], 'required' => ['nombre_comercial'], 'additionalProperties' => false],
        tiempoMaximoSegundos: 30,
    );
}

/**
 * @param  array<string, mixed>  $cuerpo
 */
function iaRespuestaAnthropic(array $cuerpo = [], int $estado = 200): void
{
    Http::fake([ClienteAnthropic::URL => Http::response([
        'type' => 'message',
        'stop_reason' => 'end_turn',
        'content' => [['type' => 'text', 'text' => '{"nombre_comercial":"Andina"}']],
        'usage' => ['input_tokens' => 1200, 'output_tokens' => 80, 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 0],
        ...$cuerpo,
    ], $estado)]);
}

/**
 * @param  array<string, mixed>  $error
 */
function iaErrorAnthropic(int $estado, array $error): void
{
    Http::fake([ClienteAnthropic::URL => Http::response(['type' => 'error', 'error' => $error], $estado)]);
}

// --- El pedido ---

it('pide a la Messages API con la clave, la versión, el modelo, el prompt, los archivos y la salida estructurada', function () {
    iaRespuestaAnthropic();

    (new ClienteAnthropic)->llamar(iaPedido(), 'clave-de-prueba');

    Http::assertSent(function (Request $pedido): bool {
        $contenido = $pedido['messages'][0]['content'];

        return $pedido->url() === ClienteAnthropic::URL
            && $pedido->method() === 'POST'
            && $pedido->header('x-api-key') === ['clave-de-prueba']
            && $pedido->header('anthropic-version') === [ClienteAnthropic::VERSION_API]
            && $pedido['model'] === 'modelo-de-prueba'
            && $pedido['max_tokens'] === 2048
            && $pedido['system'] === 'Propón el nombre comercial.'
            && $pedido['output_config'] === ['format' => ['type' => 'json_schema', 'schema' => iaPedido()->esquema]]
            && $contenido[0] === ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode('%PDF-1.4 prueba')]]
            && $contenido[1] === ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => base64_encode('png-de-prueba')]]
            && $contenido[2] === ['type' => 'text', 'text' => 'Razón social: Comercial Andina S.A.C.']
            && ! array_key_exists('thinking', $pedido->data());
    });
});

it('no acepta archivos que el modelo no lee', function () {
    expect(fn () => new ArchivoParaIa('application/zip', 'x'))->toThrow(InvalidArgumentException::class, 'solo PDF e imágenes');
});

// --- La respuesta ---

it('lee la salida estructurada y los tokens, incluidos los de la caché', function () {
    iaRespuestaAnthropic(['usage' => ['input_tokens' => 1000, 'output_tokens' => 80, 'cache_creation_input_tokens' => 150, 'cache_read_input_tokens' => 50]]);

    $respuesta = (new ClienteAnthropic)->llamar(iaPedido(), 'clave');

    expect($respuesta->resultado)->toBe(ResultadoLlamadaIa::Valida)
        ->and($respuesta->salida)->toBe(['nombre_comercial' => 'Andina'])
        ->and($respuesta->tokensEntrada)->toBe(1200)
        ->and($respuesta->tokensSalida)->toBe(80)
        ->and($respuesta->duracionMs)->toBeGreaterThanOrEqual(0);
});

it('distingue la respuesta cortada por el límite de tokens, y la cobra', function () {
    iaRespuestaAnthropic(['stop_reason' => 'max_tokens', 'content' => [['type' => 'text', 'text' => '{"nombre_com']]]);

    $respuesta = (new ClienteAnthropic)->llamar(iaPedido(), 'clave');

    expect($respuesta->resultado)->toBe(ResultadoLlamadaIa::Cortada)
        ->and($respuesta->salida)->toBeNull()
        ->and($respuesta->tokensEntrada)->toBe(1200);
});

it('marca inválida la respuesta mal formada, o la negativa del modelo, y la cobra', function (array $cuerpo) {
    iaRespuestaAnthropic($cuerpo);

    $respuesta = (new ClienteAnthropic)->llamar(iaPedido(), 'clave');

    expect($respuesta->resultado)->toBe(ResultadoLlamadaIa::Invalida)
        ->and($respuesta->salida)->toBeNull()
        ->and($respuesta->tokensSalida)->toBe(80);
})->with([
    'texto que no es JSON' => [['content' => [['type' => 'text', 'text' => 'El nombre es Andina.']]]],
    'una lista en vez de un objeto' => [['content' => [['type' => 'text', 'text' => '["Andina"]']]]],
    'sin bloque de texto' => [['content' => []]],
    'negativa' => [['stop_reason' => 'refusal', 'content' => []]],
]);

// --- Los errores ---

it('traduce cada error del proveedor a su resultado', function (int $estado, array $error, ResultadoLlamadaIa $esperado) {
    iaErrorAnthropic($estado, $error);

    $respuesta = (new ClienteAnthropic)->llamar(iaPedido(), 'clave');

    expect($respuesta->resultado)->toBe($esperado)
        ->and($respuesta->salida)->toBeNull()
        ->and($respuesta->tokensEntrada)->toBe(0);
})->with([
    'límite de pedidos' => [429, ['type' => 'rate_limit_error', 'message' => 'x'], ResultadoLlamadaIa::Saturado],
    'saturado' => [529, ['type' => 'overloaded_error', 'message' => 'x'], ResultadoLlamadaIa::Saturado],
    'clave inválida' => [401, ['type' => 'authentication_error', 'message' => 'x'], ResultadoLlamadaIa::ClaveInvalida],
    'sin permiso' => [403, ['type' => 'permission_error', 'message' => 'x'], ResultadoLlamadaIa::ClaveInvalida],
    'sin crédito' => [402, ['type' => 'billing_error', 'message' => 'x'], ResultadoLlamadaIa::SinCredito],
    'sin crédito con otro estado' => [400, ['type' => 'billing_error', 'message' => 'x'], ResultadoLlamadaIa::SinCredito],
    'sin crédito como pedido inválido' => [400, ['type' => 'invalid_request_error', 'message' => 'Your credit balance is too low to access the Anthropic API.'], ResultadoLlamadaIa::SinCredito],
    'pedido mal armado' => [400, ['type' => 'invalid_request_error', 'message' => 'x'], ResultadoLlamadaIa::Error],
    'falla del proveedor' => [500, ['type' => 'api_error', 'message' => 'x'], ResultadoLlamadaIa::Error],
    'sin tipo de error' => [529, [], ResultadoLlamadaIa::Saturado],
]);

it('marca tiempo agotado si no hay conexión o no responde a tiempo', function () {
    Http::fake(fn () => throw new ConnectionException('No respondió.'));

    expect((new ClienteAnthropic)->llamar(iaPedido(), 'clave')->resultado)->toBe(ResultadoLlamadaIa::TiempoAgotado);
});

it('solo la saturación y las fallas de la llamada pasan al respaldo; una respuesta inválida o cortada no', function () {
    expect(ResultadoLlamadaIa::Invalida->pasaAlRespaldo())->toBeFalse()
        ->and(ResultadoLlamadaIa::Cortada->pasaAlRespaldo())->toBeFalse()
        ->and(ResultadoLlamadaIa::Valida->pasaAlRespaldo())->toBeFalse()
        ->and(ResultadoLlamadaIa::Saturado->pasaAlRespaldo())->toBeTrue()
        ->and(ResultadoLlamadaIa::SinCredito->pasaAlRespaldo())->toBeTrue();
});

// --- Las claves y las implementaciones (D5) ---

it('lee la clave de la variable del proveedor; sin valor, nula; sin variable, no la necesita', function () {
    config(['nucleo.ia.claves' => ['IA_CLAVE_PRUEBA' => 'clave-de-prueba']]);

    $clientes = app(ClientesModeloLenguaje::class);

    expect($clientes->clave(IaProveedor::factory()->create(['variable_clave' => 'IA_CLAVE_PRUEBA'])))->toBe('clave-de-prueba')
        ->and($clientes->clave(IaProveedor::factory()->create(['variable_clave' => 'IA_CLAVE_SIN_VALOR'])))->toBeNull()
        ->and($clientes->clave(IaProveedor::factory()->local()->create()))->toBe('');
});

it('la configuración solo junta variables IA_CLAVE_…', function () {
    $_ENV['IA_CLAVE_DE_PRUEBA'] = 'clave';
    $_ENV['DB_PASSWORD_DE_PRUEBA'] = 'secreto';

    try {
        $claves = require config_path('nucleo.php');
    } finally {
        unset($_ENV['IA_CLAVE_DE_PRUEBA'], $_ENV['DB_PASSWORD_DE_PRUEBA']);
    }

    expect($claves['ia']['claves'])->toHaveKey('IA_CLAVE_DE_PRUEBA')->not->toHaveKey('DB_PASSWORD_DE_PRUEBA');

    foreach (array_keys($claves['ia']['claves']) as $nombre) {
        expect($nombre)->toMatch('/^IA_CLAVE_[A-Z0-9_]+$/');
    }
});

it('da el cliente de la implementación del proveedor, y la de Anthropic viene registrada', function () {
    $clientes = app(ClientesModeloLenguaje::class);
    $clientes->registrar('falso', ClienteModeloLenguajeFalso::class);

    expect($clientes->para(IaProveedor::factory()->create(['implementacion' => 'anthropic'])))->toBeInstanceOf(ClienteAnthropic::class)
        ->and($clientes->para(IaProveedor::factory()->create(['implementacion' => 'falso'])))->toBeInstanceOf(ClienteModeloLenguajeFalso::class)
        ->and(fn () => $clientes->para(IaProveedor::factory()->create(['implementacion' => 'otra'])))->toThrow(InvalidArgumentException::class, 'no está registrada')
        ->and(fn () => $clientes->registrar('anthropic', ClienteAnthropic::class))->toThrow(InvalidArgumentException::class, 'ya está registrada');
});

it('el cliente falso devuelve lo encolado, en orden, y guarda los pedidos', function () {
    $falso = (new ClienteModeloLenguajeFalso)->responderValida(['nombre_comercial' => 'Uno'])->responderValida(['nombre_comercial' => 'Dos']);

    expect($falso->llamar(iaPedido(), '')->salida)->toBe(['nombre_comercial' => 'Uno'])
        ->and($falso->llamar(iaPedido(), '')->salida)->toBe(['nombre_comercial' => 'Dos'])
        ->and($falso->pedidos)->toHaveCount(2)
        ->and(fn () => $falso->llamar(iaPedido(), ''))->toThrow(LogicException::class);
});

// --- El costo (D3) ---

it('cobra al precio por millón de tokens, con seis decimales redondeados a la mitad hacia arriba', function (int $entrada, int $salida, string $esperado) {
    $precio = IaPrecioModelo::query()->create(['modelo_id' => IaModelo::factory()->create()->id, 'vigente_desde' => '2026-10-01', 'usd_millon_entrada' => '3', 'usd_millon_salida' => '15']);

    expect(CostoLlamadaIa::costo($precio, $entrada, $salida))->toBe($esperado);
})->with([
    'mil de entrada y doscientos de salida' => [1000, 200, '0.006000'],
    'un millón de cada uno' => [1_000_000, 1_000_000, '18.000000'],
    'menos de medio millonésimo' => [0, 0, '0.000000'],
    'un token de entrada' => [1, 0, '0.000003'],
]);

it('redondea la mitad hacia arriba', function () {
    $precio = IaPrecioModelo::query()->create(['modelo_id' => IaModelo::factory()->create()->id, 'vigente_desde' => '2026-10-01', 'usd_millon_entrada' => '0.1', 'usd_millon_salida' => '0']);

    expect(CostoLlamadaIa::costo($precio, 4, 0))->toBe('0.000000')
        ->and(CostoLlamadaIa::costo($precio, 5, 0))->toBe('0.000001');
});

it('sin precio vigente no cobra', function () {
    expect(CostoLlamadaIa::costo(null, 1000, 1000))->toBe('0.000000');
});

it('toma el precio vigente el día de la llamada en Lima, no el de después', function () {
    $modelo = IaModelo::factory()->create();
    $octubre = IaPrecioModelo::query()->create(['modelo_id' => $modelo->id, 'vigente_desde' => '2026-10-01', 'usd_millon_entrada' => '3', 'usd_millon_salida' => '15']);
    $noviembre = IaPrecioModelo::query()->create(['modelo_id' => $modelo->id, 'vigente_desde' => '2026-11-01', 'usd_millon_entrada' => '2', 'usd_millon_salida' => '10']);

    // 31 de octubre a las 23:30 en Lima ya es 1 de noviembre en UTC.
    $ultimaNocheDeOctubre = CarbonImmutable::parse('2026-11-01 04:30', 'UTC');

    expect(CostoLlamadaIa::precioVigente($modelo->id, $ultimaNocheDeOctubre)?->id)->toBe($octubre->id)
        ->and(CostoLlamadaIa::precioVigente($modelo->id, CarbonImmutable::parse('2026-11-01 12:00', 'America/Lima'))?->id)->toBe($noviembre->id)
        ->and(CostoLlamadaIa::precioVigente($modelo->id, CarbonImmutable::parse('2026-09-30 12:00', 'America/Lima')))->toBeNull();
});
