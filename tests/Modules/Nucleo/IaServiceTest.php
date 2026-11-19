<?php

declare(strict_types=1);

use App\Modules\Nucleo\Alertas\AlertasDelNucleo;
use App\Modules\Nucleo\Enums\ResultadoLlamadaIa;
use App\Modules\Nucleo\Enums\ResultadoPedidoIa;
use App\Modules\Nucleo\Enums\SeguridadCampoIa;
use App\Modules\Nucleo\Ia\CitaIa;
use App\Modules\Nucleo\Ia\ClienteModeloLenguajeFalso;
use App\Modules\Nucleo\Ia\ClientesModeloLenguaje;
use App\Modules\Nucleo\Ia\DefinicionProcesoIa;
use App\Modules\Nucleo\Ia\PromptsIa;
use App\Modules\Nucleo\Ia\RegistroProcesosIa;
use App\Modules\Nucleo\Ia\RespuestaModeloIa;
use App\Modules\Nucleo\Ia\SalidaIa;
use App\Modules\Nucleo\Ia\SeguridadPorCampo;
use App\Modules\Nucleo\Ia\SinCupoIa;
use App\Modules\Nucleo\Ia\TextoDePdf;
use App\Modules\Nucleo\Ia\ValidadorEsquemaIa;
use App\Modules\Nucleo\Jobs\SugerirConIa;
use App\Modules\Nucleo\Models\Adjunto;
use App\Modules\Nucleo\Models\AlertaEmitida;
use App\Modules\Nucleo\Models\IaLlamada;
use App\Modules\Nucleo\Models\IaModelo;
use App\Modules\Nucleo\Models\IaPrecioModelo;
use App\Modules\Nucleo\Models\IaProceso;
use App\Modules\Nucleo\Models\IaProveedor;
use App\Modules\Nucleo\Models\IaSugerencia;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\TipoAdjunto;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\IaConfiguracionService;
use App\Modules\Nucleo\Services\IaService;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

/*
 * Pedir una sugerencia a la IA (plan 1.11, paso 4): los candados, la llamada con su reintento y su respaldo, el registro
 * de cada llamada, el tope, la evidencia y la seguridad por campo. Nunca el servicio real: el cliente falso responde lo
 * que la prueba le encola. Personas, empresas y documentos inventados; los PDF se generan aquí.
 */

/**
 * El proceso de ejemplo: propone el nombre comercial de una empresa a partir de su razón social y, si se le da, de un
 * PDF de tipo «bases_prueba». La regla del campo: no vacío y sin «S.A.C.».
 */
function sugDefinicion(array $camposEnviados = ['party' => ['razon_social']]): DefinicionProcesoIa
{
    return new DefinicionProcesoIa('ejemplo_ia', 'Nombre comercial', 'Propone el nombre comercial.', 'Comercial', ['party'], ['bases_prueba'], $camposEnviados, [
        'nombre_comercial' => ['type' => 'string'],
    ], permisoResolver: 'parties.editar', produce: ['party'], reglas: ['nombre_comercial' => fn (mixed $valor): bool => is_string($valor) && trim($valor) !== '' && ! str_contains($valor, 'S.A.C.')]);
}

/** Un modelo de un proveedor externo con la implementación falsa, su clave y su precio. */
function sugModelo(string $usdEntrada = '3', string $usdSalida = '15', int $paralelo = 2): IaModelo
{
    $proveedor = IaProveedor::factory()->create(['llamadas_en_paralelo' => $paralelo]);
    $modelo = IaModelo::factory()->create(['proveedor_id' => $proveedor->id]);
    IaPrecioModelo::query()->create(['modelo_id' => $modelo->id, 'vigente_desde' => '2026-01-01', 'usd_millon_entrada' => $usdEntrada, 'usd_millon_salida' => $usdSalida]);
    config(['nucleo.ia.claves' => [...config('nucleo.ia.claves', []), $proveedor->variable_clave => 'clave-de-prueba']]);

    return $modelo;
}

/** Registra el proceso, le asigna modelos, Gerencia lo autoriza, se le pone tope y se enciende. */
function sugEncendido(?IaModelo $modelo, ?IaModelo $respaldo = null, string $tope = '10.00'): IaProceso
{
    $configuracion = app(IaConfiguracionService::class);
    $proceso = IaProceso::query()->where('codigo', 'ejemplo_ia')->firstOrFail();
    $configuracion->asignarModelos($proceso->id, $modelo?->id, $respaldo?->id, $proceso->refresh()->version);
    $configuracion->autorizar($proceso->id, $tope, $proceso->refresh()->version);
    $proceso->refresh()->update(['esta_activo' => true]);

    return $proceso->refresh();
}

function sugEmpresa(): Party
{
    return Party::factory()->create(['razon_social' => 'Comercial Andina del Norte S.A.C.']);
}

function sugFalso(): ClienteModeloLenguajeFalso
{
    return app(ClienteModeloLenguajeFalso::class);
}

/**
 * Una salida bien formada.
 *
 * @param  array{pagina: ?int, texto: ?string, campo: ?string}  $evidencia
 * @return array<string, mixed>
 */
function sugSalida(string $valor = 'Andina del Norte', array $evidencia = ['pagina' => null, 'texto' => null, 'campo' => 'razon_social'], string $seguridad = 'alta'): array
{
    return ['campos' => ['nombre_comercial' => ['valor' => $valor, 'evidencia' => $evidencia, 'seguridad' => $seguridad]], 'explicacion' => 'Sale de la razón social, sin la forma societaria.'];
}

function sugRespuesta(ResultadoLlamadaIa $resultado, int $entrada = 500, int $salida = 0): RespuestaModeloIa
{
    return new RespuestaModeloIa($resultado, $entrada, $salida, 300);
}

/** Pide y ejecuta en el momento (la cola de pruebas es síncrona). */
function sugPedir(Party $empresa, ?Adjunto $adjunto = null): ResultadoPedidoIa
{
    return app(IaService::class)->sugerir('ejemplo_ia', $empresa, $adjunto);
}

/**
 * Un PDF de prueba con una página por texto; cada «\n» es otra línea. En Helvetica con WinAnsi.
 *
 * @param  list<string>  $paginas
 */
function sugPdf(array $paginas): string
{
    $objetos = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];
    $hijos = [];

    foreach ($paginas as $texto) {
        $lineas = array_map(fn (string $linea): string => '('.addcslashes((string) mb_convert_encoding($linea, 'Windows-1252', 'UTF-8'), '()\\').') Tj', explode("\n", $texto));
        $contenido = 'BT /F1 12 Tf 14 TL 72 720 Td '.implode(' T* ', $lineas).' ET';
        $flujo = count($objetos) + 1;
        $objetos[$flujo] = '<< /Length '.strlen($contenido)." >>\nstream\n{$contenido}\nendstream";
        $pagina = $flujo + 1;
        $objetos[$pagina] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents {$flujo} 0 R /Resources << /Font << /F1 3 0 R >> >> >>";
        $hijos[] = "{$pagina} 0 R";
    }

    $objetos[2] = '<< /Type /Pages /Kids ['.implode(' ', $hijos).'] /Count '.count($hijos).' >>';
    $pdf = "%PDF-1.4\n";
    $posiciones = [];

    foreach ($objetos as $numero => $objeto) {
        $posiciones[$numero] = strlen($pdf);
        $pdf .= "{$numero} 0 obj\n{$objeto}\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= 'xref
0 '.(count($objetos) + 1)."\n0000000000 65535 f \n";

    foreach ($posiciones as $posicion) {
        $pdf .= sprintf("%010d 00000 n \n", $posicion);
    }

    return $pdf.'trailer << /Size '.(count($objetos) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
}

/** Un adjunto de la empresa con ese contenido, en el disco falso. */
function sugAdjunto(Party $empresa, string $contenido, string $mime = 'application/pdf', ?string $grupoSensible = null): Adjunto
{
    $tipo = TipoAdjunto::query()->where('codigo', 'bases_prueba')->first() ?? TipoAdjunto::factory()->create(['codigo' => 'bases_prueba']);
    $ruta = 'party/2026/10/'.fake()->uuid().'.pdf';
    Storage::disk('adjuntos')->put($ruta, $contenido);

    return Adjunto::query()->create([
        'adjuntable_type' => 'party', 'adjuntable_id' => $empresa->id, 'tipo_adjunto_id' => $tipo->id, 'nombre_original' => 'bases.pdf',
        'ruta' => $ruta, 'mime' => $mime, 'tamano_bytes' => strlen($contenido), 'hash' => hash('sha256', $contenido), 'disco' => 'adjuntos',
        'grupo_sensible' => $grupoSensible,
    ]);
}

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake('adjuntos');
    Sleep::fake();
    $this->actingAs(User::factory()->create());
    app(ClientesModeloLenguaje::class)->registrar('falso', ClienteModeloLenguajeFalso::class);
    app()->instance(ClienteModeloLenguajeFalso::class, new ClienteModeloLenguajeFalso);
    app()->instance(PromptsIa::class, new PromptsIa(base_path('tests/Modules/Nucleo/Fixtures/PromptsIa')));
    app(RegistroProcesosIa::class)->registrar(sugDefinicion());
    app(IaConfiguracionService::class)->sincronizar();
});

// --- Una sugerencia válida (D3, D6, D8) ---

it('con todo en orden, guarda la sugerencia con su número, su llamada medida y la seguridad de cada campo', function () {
    sugEncendido(sugModelo());
    $empresa = sugEmpresa();
    sugFalso()->responderValida(sugSalida(), tokensEntrada: 1000, tokensSalida: 200);

    expect(sugPedir($empresa))->toBe(ResultadoPedidoIa::Encolado);

    $sugerencia = IaSugerencia::query()->sole();
    $llamada = IaLlamada::query()->sole();

    expect($sugerencia->numero)->toBe('SUG-'.now('America/Lima')->year.'-000001')
        ->and($sugerencia->origen_type)->toBe('party')
        ->and($sugerencia->origen_id)->toBe($empresa->id)
        ->and($sugerencia->llamada_id)->toBe($llamada->id)
        // jsonb no guarda el orden de las claves.
        ->and($sugerencia->propuesta)->toEqual(['nombre_comercial' => ['valor' => 'Andina del Norte', 'evidencia' => ['pagina' => null, 'texto' => null, 'campo' => 'razon_social']]])
        // Regla cumplida y evidencia encontrada entre los campos enviados: alta.
        ->and($sugerencia->seguridad_por_campo)->toBe(['nombre_comercial' => 'alta'])
        ->and($sugerencia->explicacion)->toBe('Sale de la razón social, sin la forma societaria.')
        ->and($llamada->resultado)->toBe(ResultadoLlamadaIa::Valida)
        ->and($llamada->prompt_version)->toBe(2)
        ->and($llamada->tokens_entrada)->toBe(1000)
        ->and($llamada->tokens_salida)->toBe(200)
        // 1000 × 3 + 200 × 15 = 6000 por millón.
        ->and($llamada->costo_usd)->toBe('0.006000')
        ->and($llamada->es_respaldo)->toBeFalse();
});

it('al modelo le llegan el prompt vigente, solo los campos enviados y el esquema de la salida', function () {
    sugEncendido(sugModelo());
    $empresa = sugEmpresa();
    sugFalso()->responderValida(sugSalida());
    sugPedir($empresa);

    $pedido = sugFalso()->pedidos[0];

    expect($pedido->prompt)->toBe('Propón el nombre comercial del registro y di de dónde lo sacaste.')
        ->and(json_decode($pedido->contexto, true))->toBe(['registro' => ['razon_social' => 'Comercial Andina del Norte S.A.C.']])
        ->and($pedido->esquema)->toBe(SalidaIa::esquema(sugDefinicion()))
        ->and($pedido->archivos)->toBe([]);
});

it('cada sugerencia toma el siguiente número del año', function () {
    sugEncendido(sugModelo());
    sugFalso()->responderValida(sugSalida())->responderValida(sugSalida());
    sugPedir(sugEmpresa());
    sugPedir(sugEmpresa());

    expect(IaSugerencia::query()->orderBy('id')->pluck('numero')->all())
        ->toBe(['SUG-'.now('America/Lima')->year.'-000001', 'SUG-'.now('America/Lima')->year.'-000002']);
});

it('sugerir encola el pedido con quién lo hizo, después del commit', function () {
    Queue::fake();
    sugEncendido(sugModelo());
    $empresa = sugEmpresa();

    expect(sugPedir($empresa))->toBe(ResultadoPedidoIa::Encolado);

    Queue::assertPushed(SugerirConIa::class, fn (SugerirConIa $job): bool => $job->proceso === 'ejemplo_ia' && $job->origenType === 'party'
        && $job->origenId === $empresa->id && $job->adjuntoId === null && $job->pedidoPor === auth()->id());
});

// --- Los candados (D8): en todos, camino manual sin llamar ---

it('no llama si el proceso está apagado, sin modelo, en su tope o sin clave', function () {
    $empresa = sugEmpresa();
    expect(sugPedir($empresa))->toBe(ResultadoPedidoIa::Apagado);

    // Sin modelo propio ni por defecto: no envía nada fuera, se puede encender, pero no tiene con qué llamar.
    sugEncendido(null);
    expect(sugPedir($empresa))->toBe(ResultadoPedidoIa::SinModelo);

    $proceso = sugEncendido(sugModelo(), tope: '0.00');
    expect(sugPedir($empresa))->toBe(ResultadoPedidoIa::TopeAlcanzado);

    $proceso->update(['tope_mensual_usd' => '10.00']);
    config(['nucleo.ia.claves' => []]);
    expect(sugPedir($empresa))->toBe(ResultadoPedidoIa::SinClave)
        ->and(IaLlamada::query()->count())->toBe(0)
        ->and(sugFalso()->pedidos)->toBe([]);
});

it('no llama si lo que envía ya no es lo que Gerencia autorizó', function () {
    sugEncendido(sugModelo());
    // El módulo cambió lo que el proceso envía, sin pasar por la configuración: la huella ya no coincide.
    app()->instance(RegistroProcesosIa::class, tap(new RegistroProcesosIa)->registrar(sugDefinicion(['party' => ['razon_social', 'nombre_comercial']])));

    expect(sugPedir(sugEmpresa()))->toBe(ResultadoPedidoIa::SinAutorizacion)
        ->and(IaLlamada::query()->count())->toBe(0);
});

it('no llama con un origen que el proceso no declara, ni con un adjunto sensible, quitado, de otro registro o de otro tipo', function () {
    sugEncendido(sugModelo());
    $empresa = sugEmpresa();
    $otra = sugEmpresa();

    expect(app(IaService::class)->sugerir('ejemplo_ia', User::factory()->create()))->toBe(ResultadoPedidoIa::OrigenNoPermitido)
        ->and(sugPedir($empresa, sugAdjunto($empresa, sugPdf(['Bases sensibles']), grupoSensible: 'costos')))->toBe(ResultadoPedidoIa::AdjuntoNoPermitido)
        ->and(sugPedir($empresa, sugAdjunto($otra, sugPdf(['Bases']))))->toBe(ResultadoPedidoIa::AdjuntoNoPermitido)
        ->and(sugPedir($empresa, sugAdjunto($empresa, 'texto', 'text/plain')))->toBe(ResultadoPedidoIa::AdjuntoNoPermitido);

    $quitado = sugAdjunto($empresa, sugPdf(['Bases quitadas']));
    $quitado->forceFill(['quitado_at' => now(), 'quitado_por_user_id' => auth()->id()])->save();
    $deOtroTipo = sugAdjunto($empresa, sugPdf(['Bases de otro tipo']));
    $deOtroTipo->update(['tipo_adjunto_id' => TipoAdjunto::factory()->create()->id]);

    expect(sugPedir($empresa, $quitado))->toBe(ResultadoPedidoIa::AdjuntoNoPermitido)
        ->and(sugPedir($empresa, $deOtroTipo))->toBe(ResultadoPedidoIa::AdjuntoNoPermitido)
        ->and(IaLlamada::query()->count())->toBe(0);
});

it('vuelve a revisar los candados al ejecutar: si lo apagaron mientras esperaba en la cola, no llama', function () {
    $proceso = sugEncendido(sugModelo());
    $empresa = sugEmpresa();
    $proceso->update(['esta_activo' => false]);

    expect(app(IaService::class)->ejecutar('ejemplo_ia', 'party', $empresa->id, null))->toBeNull()
        ->and(IaLlamada::query()->count())->toBe(0);
});

// --- Lo que sale mal (D5, D7, D9) ---

it('una respuesta mal formada o cortada queda en las llamadas, cuenta su costo y no crea sugerencia ni pasa al respaldo', function () {
    sugEncendido(sugModelo(), sugModelo());
    sugFalso()->responderValida(['campos' => [], 'explicacion' => 'Falta el campo.'])
        ->responder(sugRespuesta(ResultadoLlamadaIa::Cortada, 800, 4096));
    sugPedir(sugEmpresa());
    sugPedir(sugEmpresa());

    expect(IaLlamada::query()->orderBy('id')->pluck('resultado')->all())->toBe([ResultadoLlamadaIa::Invalida, ResultadoLlamadaIa::Cortada])
        ->and(IaLlamada::query()->where('es_respaldo', true)->count())->toBe(0)
        ->and(IaLlamada::query()->sum('costo_usd'))->toBeGreaterThan(0)
        ->and(IaSugerencia::query()->count())->toBe(0);
});

it('una explicación vacía o de más de 300 caracteres también es inválida', function () {
    $salida = sugSalida();

    expect(SalidaIa::esValida(sugDefinicion(), [...$salida, 'explicacion' => '  ']))->toBeFalse()
        ->and(SalidaIa::esValida(sugDefinicion(), [...$salida, 'explicacion' => str_repeat('a', 301)]))->toBeFalse()
        ->and(SalidaIa::esValida(sugDefinicion(), $salida))->toBeTrue();
});

it('saturado: un solo reintento corto, y cada intento queda en las llamadas', function () {
    sugEncendido(sugModelo());
    sugFalso()->responder(sugRespuesta(ResultadoLlamadaIa::Saturado))->responderValida(sugSalida());
    sugPedir(sugEmpresa());

    expect(IaLlamada::query()->orderBy('id')->get(['intento', 'resultado'])->map(fn (IaLlamada $l): array => [$l->intento, $l->resultado->value])->all())
        ->toBe([[1, 'saturado'], [2, 'valida']])
        ->and(IaSugerencia::query()->count())->toBe(1);
    Sleep::assertSleptTimes(1);
});

it('si el principal falla, usa el de respaldo y la sugerencia es de esa llamada', function () {
    $principal = sugModelo();
    $respaldo = sugModelo();
    sugEncendido($principal, $respaldo);
    sugFalso()->responder(sugRespuesta(ResultadoLlamadaIa::Error))->responderValida(sugSalida());
    sugPedir(sugEmpresa());

    $llamadas = IaLlamada::query()->orderBy('id')->get();

    expect($llamadas->pluck('modelo_id')->all())->toBe([$principal->id, $respaldo->id])
        ->and($llamadas->pluck('es_respaldo')->all())->toBe([false, true])
        ->and(IaSugerencia::query()->sole()->llamada_id)->toBe($llamadas[1]->id);
});

it('sin crédito o con la clave inválida no reintenta, avisa a su responsable una vez al día y pasa al respaldo', function () {
    $principal = sugModelo();
    sugEncendido($principal, sugModelo());
    sugFalso()->responder(sugRespuesta(ResultadoLlamadaIa::SinCredito, 0))->responderValida(sugSalida())
        ->responder(sugRespuesta(ResultadoLlamadaIa::SinCredito, 0))->responderValida(sugSalida());
    sugPedir(sugEmpresa());
    sugPedir(sugEmpresa());

    expect(IaLlamada::query()->where('resultado', 'sin_credito')->pluck('intento')->all())->toBe([1, 1])
        ->and(IaSugerencia::query()->count())->toBe(2)
        ->and(AlertaEmitida::query()->where('clave', 'like', AlertasDelNucleo::IA_PROVEEDOR_NO_DISPONIBLE.':proveedor:'.$principal->proveedor_id.':sin_credito:%')->count())->toBe(1);
});

it('al llegar al tope avisa una vez, queda apagado hasta el mes siguiente y no pasa al respaldo', function () {
    // 1 millón de tokens de entrada a 3 dólares: el tope de 2 se pasa en la primera llamada.
    sugEncendido(sugModelo(), sugModelo(), tope: '2.00');
    $empresa = sugEmpresa();
    sugFalso()->responder(sugRespuesta(ResultadoLlamadaIa::Error, 1_000_000));
    sugPedir($empresa);

    expect(IaLlamada::query()->count())->toBe(1)
        ->and(AlertaEmitida::query()->where('clave', 'like', AlertasDelNucleo::IA_TOPE_ALCANZADO.':proceso:%')->count())->toBe(1)
        ->and(sugPedir($empresa))->toBe(ResultadoPedidoIa::TopeAlcanzado);

    $this->travelTo(now('America/Lima')->addMonthNoOverflow()->startOfMonth()->addDay());
    sugFalso()->responderValida(sugSalida());

    expect(sugPedir($empresa))->toBe(ResultadoPedidoIa::Encolado)
        ->and(IaSugerencia::query()->count())->toBe(1);
});

it('sin cupo en el proveedor, el job vuelve a la cola sin llamar', function () {
    $modelo = sugModelo(paralelo: 1);
    sugEncendido($modelo);
    $empresa = sugEmpresa();
    $ocupado = Cache::lock("ia-proveedor:{$modelo->proveedor_id}:cupo:0", 60);
    $ocupado->get();

    expect(fn () => app(IaService::class)->ejecutar('ejemplo_ia', 'party', $empresa->id, null))->toThrow(SinCupoIa::class)
        ->and(IaLlamada::query()->count())->toBe(0);

    $ocupado->release();
});

// --- Sin precio no hay llamada; sin cuenta, el respaldo es de otro proveedor (ajustes del 2026-10-28) ---

it('sin precio vigente no llama: camino manual y aviso de proveedor no disponible, una vez al día', function () {
    $modelo = sugModelo();
    sugEncendido($modelo);
    // El precio se quitó después de asignar el modelo.
    IaPrecioModelo::query()->where('modelo_id', $modelo->id)->delete();
    $empresa = sugEmpresa();

    expect(sugPedir($empresa))->toBe(ResultadoPedidoIa::SinPrecio)
        ->and(sugPedir($empresa))->toBe(ResultadoPedidoIa::SinPrecio)
        ->and(IaLlamada::query()->count())->toBe(0)
        ->and(AlertaEmitida::query()->where('clave', 'like', AlertasDelNucleo::IA_PROVEEDOR_NO_DISPONIBLE.':proveedor:'.$modelo->proveedor_id.':sin-precio:%')->count())->toBe(1);
});

it('un respaldo sin precio vigente no se llama, y avisa', function () {
    $respaldo = sugModelo();
    sugEncendido(sugModelo(), $respaldo);
    IaPrecioModelo::query()->where('modelo_id', $respaldo->id)->delete();
    sugFalso()->responder(sugRespuesta(ResultadoLlamadaIa::Error));
    sugPedir(sugEmpresa());

    expect(IaLlamada::query()->pluck('es_respaldo')->all())->toBe([false])
        ->and(AlertaEmitida::query()->where('clave', 'like', '%:proveedor:'.$respaldo->proveedor_id.':sin-precio:%')->count())->toBe(1);
});

it('la llamada queda con el precio vigente al llamar, y un modelo propio cuesta 0', function () {
    $proveedor = IaProveedor::factory()->create(['es_externo' => false, 'variable_clave' => null]);
    $local = IaModelo::factory()->create(['proveedor_id' => $proveedor->id]);
    $precio = IaPrecioModelo::query()->create(['modelo_id' => $local->id, 'vigente_desde' => '2026-01-01', 'usd_millon_entrada' => '0', 'usd_millon_salida' => '0']);
    sugEncendido($local);
    sugFalso()->responderValida(sugSalida(), 50_000, 5_000);
    sugPedir(sugEmpresa());

    expect(IaLlamada::query()->sole()->only(['precio_id', 'costo_usd']))->toBe(['precio_id' => $precio->id, 'costo_usd' => '0.000000']);
});

it('sin crédito o con la clave inválida, un respaldo del mismo proveedor no se llama: fallaría igual', function (ResultadoLlamadaIa $resultado) {
    $principal = sugModelo();
    $mismoProveedor = IaModelo::factory()->create(['proveedor_id' => $principal->proveedor_id]);
    IaPrecioModelo::query()->create(['modelo_id' => $mismoProveedor->id, 'vigente_desde' => '2026-01-01', 'usd_millon_entrada' => '1', 'usd_millon_salida' => '5']);
    sugEncendido($principal, $mismoProveedor);
    sugFalso()->responder(sugRespuesta($resultado, 0));
    sugPedir(sugEmpresa());

    expect(IaLlamada::query()->pluck('modelo_id')->all())->toBe([$principal->id])
        ->and(IaSugerencia::query()->count())->toBe(0);
})->with([ResultadoLlamadaIa::SinCredito, ResultadoLlamadaIa::ClaveInvalida]);

it('con un error cualquiera, el respaldo del mismo proveedor sí se llama', function () {
    $principal = sugModelo();
    $mismoProveedor = IaModelo::factory()->create(['proveedor_id' => $principal->proveedor_id]);
    IaPrecioModelo::query()->create(['modelo_id' => $mismoProveedor->id, 'vigente_desde' => '2026-01-01', 'usd_millon_entrada' => '1', 'usd_millon_salida' => '5']);
    sugEncendido($principal, $mismoProveedor);
    sugFalso()->responder(sugRespuesta(ResultadoLlamadaIa::Error))->responderValida(sugSalida());
    sugPedir(sugEmpresa());

    expect(IaLlamada::query()->orderBy('id')->pluck('modelo_id')->all())->toBe([$principal->id, $mismoProveedor->id])
        ->and(IaSugerencia::query()->count())->toBe(1);
});

// --- La evidencia y la seguridad (D6) ---

it('con un PDF, la cita se busca en su página sin importar saltos de línea, guiones de corte ni mayúsculas', function () {
    sugEncendido(sugModelo());
    $empresa = sugEmpresa();
    $adjunto = sugAdjunto($empresa, sugPdf(['Portada', "El postor es ANDINA DEL\nNOR-\nTE, con domicilio en Chiclayo."]));
    sugFalso()->responderValida(sugSalida(evidencia: ['pagina' => 2, 'texto' => 'andina del norte, con domicilio', 'campo' => null]))
        ->responderValida(sugSalida(evidencia: ['pagina' => 1, 'texto' => 'andina del norte', 'campo' => null]))
        ->responderValida(sugSalida(evidencia: ['pagina' => 9, 'texto' => 'andina del norte', 'campo' => null]));
    sugPedir($empresa, $adjunto);
    sugPedir($empresa, $adjunto);
    sugPedir($empresa, $adjunto);

    expect(IaSugerencia::query()->orderBy('id')->pluck('seguridad_por_campo')->pluck('nombre_comercial')->all())->toBe(['alta', 'baja', 'baja'])
        ->and(sugFalso()->pedidos[0]->archivos[0]->tipo)->toBe('application/pdf');
});

it('un PDF que no se puede leer deja el campo en seguridad baja, y el proceso sigue', function () {
    sugEncendido(sugModelo());
    $empresa = sugEmpresa();
    sugFalso()->responderValida(sugSalida(evidencia: ['pagina' => 1, 'texto' => 'andina', 'campo' => null]));
    sugPedir($empresa, sugAdjunto($empresa, '%PDF-1.4 roto'));

    expect(IaSugerencia::query()->sole()->seguridad_por_campo)->toBe(['nombre_comercial' => 'baja']);
});

it('sin adjunto, la evidencia debe ser un campo de los enviados; lo que diga el modelo solo baja la seguridad', function () {
    sugEncendido(sugModelo());
    $empresa = sugEmpresa();
    sugFalso()->responderValida(sugSalida(evidencia: ['pagina' => null, 'texto' => null, 'campo' => 'telefono']))
        ->responderValida(sugSalida(seguridad: 'media'))
        ->responderValida(sugSalida('Comercial Andina S.A.C.', seguridad: 'alta'));
    sugPedir($empresa);
    sugPedir($empresa);
    sugPedir($empresa);

    expect(IaSugerencia::query()->orderBy('id')->pluck('seguridad_por_campo')->pluck('nombre_comercial')->all())->toBe(['baja', 'media', 'baja']);
});

it('la seguridad sale de la regla y la evidencia: alta con las dos, media con una, baja sin ninguna o si una falla', function () {
    $alta = SeguridadCampoIa::Alta;

    expect(SeguridadPorCampo::calcular(true, true, $alta))->toBe(SeguridadCampoIa::Alta)
        ->and(SeguridadPorCampo::calcular(null, true, $alta))->toBe(SeguridadCampoIa::Media)
        ->and(SeguridadPorCampo::calcular(true, null, $alta))->toBe(SeguridadCampoIa::Media)
        ->and(SeguridadPorCampo::calcular(null, null, $alta))->toBe(SeguridadCampoIa::Baja)
        ->and(SeguridadPorCampo::calcular(false, true, $alta))->toBe(SeguridadCampoIa::Baja)
        ->and(SeguridadPorCampo::calcular(true, false, $alta))->toBe(SeguridadCampoIa::Baja)
        // El modelo la baja, nunca la sube.
        ->and(SeguridadPorCampo::calcular(true, true, SeguridadCampoIa::Baja))->toBe(SeguridadCampoIa::Baja)
        ->and(SeguridadPorCampo::calcular(null, true, $alta))->toBe(SeguridadCampoIa::Media);
});

it('la cita normalizada ignora espacios, saltos, guiones de corte, guion blando, ligaduras y mayúsculas', function () {
    expect(CitaIa::estaEn('presupuesto referencial', "El PRESU-\nPUESTO   Referencial es"))->toBeTrue()
        ->and(CitaIa::estaEn('oficina', 'O'."\u{FB01}".'cina central'))->toBeTrue()
        ->and(CitaIa::estaEn('certificado', "certi\u{00AD}ficado"))->toBeTrue()
        ->and(CitaIa::estaEn('plazo de ejecución', "plazo\n\nde\tejecución"))->toBeTrue()
        ->and(CitaIa::estaEn('plazo de entrega', 'plazo de ejecución'))->toBeFalse()
        ->and(CitaIa::estaEn('   ', 'cualquier texto'))->toBeFalse();
});

it('el lector de PDF da el texto de cada página; uno roto o vacío, nulo', function () {
    $paginas = (new TextoDePdf)->paginas(sugPdf(['Primera página', "Segunda\npágina"]));

    expect($paginas)->toHaveCount(2)
        ->and(CitaIa::estaEn('primera página', $paginas[0] ?? ''))->toBeTrue()
        ->and(CitaIa::estaEn('segunda página', $paginas[1] ?? ''))->toBeTrue()
        ->and((new TextoDePdf)->paginas('no es un pdf'))->toBeNull()
        ->and((new TextoDePdf)->paginas(''))->toBeNull();
});

it('una imagen no se puede verificar: el campo queda en media si cumple su regla', function () {
    sugEncendido(sugModelo());
    $empresa = sugEmpresa();
    sugFalso()->responderValida(sugSalida(evidencia: ['pagina' => 1, 'texto' => null, 'campo' => null]));
    sugPedir($empresa, sugAdjunto($empresa, 'imagen', 'image/png'));

    expect(IaSugerencia::query()->sole()->seguridad_por_campo)->toBe(['nombre_comercial' => 'media']);
});

it('el validador de la salida rechaza campos de más, de menos y de otro tipo', function () {
    $esquema = SalidaIa::esquema(sugDefinicion());
    $salida = sugSalida();

    expect(ValidadorEsquemaIa::valida($salida, $esquema))->toBeTrue()
        ->and(ValidadorEsquemaIa::valida([...$salida, 'otro' => 1], $esquema))->toBeFalse()
        ->and(ValidadorEsquemaIa::valida(['explicacion' => 'x'], $esquema))->toBeFalse()
        ->and(ValidadorEsquemaIa::valida(sugSalida(evidencia: ['pagina' => 'uno', 'texto' => null, 'campo' => null]), $esquema))->toBeFalse()
        ->and(ValidadorEsquemaIa::valida(sugSalida(seguridad: 'segura'), $esquema))->toBeFalse()
        ->and(ValidadorEsquemaIa::valida(['campos' => ['nombre_comercial' => ['valor' => 7, 'evidencia' => ['pagina' => null, 'texto' => null, 'campo' => null], 'seguridad' => 'alta']], 'explicacion' => 'x'], $esquema))->toBeFalse();
});

it('una regla de un campo que el proceso no propone falla al declararlo', function () {
    expect(fn () => new DefinicionProcesoIa('raro_ia', 'Raro', 'Prueba.', 'Comercial', ['party'], [], [], ['dato' => ['type' => 'string']], permisoResolver: 'parties.editar', produce: ['party'], reglas: ['otro' => fn (): bool => true]))
        ->toThrow(InvalidArgumentException::class, 'no es de un campo que propone');
});

// --- Revisión de arquitectura: un reintento nunca paga dos veces; lo que sale, declarado ---

it('un doble pedido igual se encola una sola vez, y el job no repite tras una excepción', function () {
    Queue::fake();
    sugEncendido(sugModelo());
    $empresa = sugEmpresa();

    sugPedir($empresa);
    sugPedir($empresa);

    Queue::assertPushed(SugerirConIa::class, 1);

    $job = new SugerirConIa('ejemplo_ia', 'party', $empresa->id, null, auth()->id());

    expect($job->uniqueId())->toBe("ejemplo_ia:party:{$empresa->id}:-")
        ->and($job->maxExceptions)->toBe(1)
        ->and($job->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($job->timeout)->toBeLessThan(SugerirConIa::RESERVA)
        ->and($job->uniqueFor)->toBe(SugerirConIa::RESERVA);
});

it('si el pedido falla, avisa al responsable una vez al día por proceso', function () {
    $job = new SugerirConIa('ejemplo_ia', 'party', 1, null, auth()->id());

    $job->failed(new RuntimeException('Falló.'));
    $job->failed(new RuntimeException('Otra vez.'));

    expect(AlertaEmitida::query()->where('clave', 'like', AlertasDelNucleo::IA_PEDIDO_FALLIDO.':proceso:ejemplo_ia:%')->count())->toBe(1);
});

it('las opciones salen solo con las listas y campos declarados, que entran en la huella', function () {
    $opciones = fn (): array => ['partidas' => [['codigo' => '01.02', 'nombre' => 'Cableado', 'monto' => '15000.00']], 'otra' => [['x' => 1]]];
    $definicion = new DefinicionProcesoIa('opciones_ia', 'Opciones', 'Prueba.', 'Comercial', ['party'], [], ['party' => ['razon_social']], [
        'nombre_comercial' => ['type' => 'string'],
    ], permisoResolver: 'parties.editar', produce: ['party'], opciones: $opciones, opcionesEnviadas: ['partidas' => ['codigo', 'nombre']]);
    $conMonto = new DefinicionProcesoIa('opciones_ia', 'Opciones', 'Prueba.', 'Comercial', ['party'], [], ['party' => ['razon_social']], [
        'nombre_comercial' => ['type' => 'string'],
    ], permisoResolver: 'parties.editar', produce: ['party'], opciones: $opciones, opcionesEnviadas: ['partidas' => ['codigo', 'nombre', 'monto']]);

    expect($definicion->huella())->not->toBe($conMonto->huella())
        ->and(sugDefinicion()->huella())->toBe((new DefinicionProcesoIa('ejemplo_ia', 'Otro nombre', 'Otra.', 'Otro', ['party'], ['bases_prueba'], ['party' => ['razon_social']], [
            'nombre_comercial' => ['type' => 'string'],
        ], permisoResolver: 'parties.editar', produce: ['party']))->huella())
        ->and(fn () => new DefinicionProcesoIa('mal_ia', 'Mal', 'Prueba.', 'Comercial', ['party'], [], [], ['dato' => ['type' => 'string']], permisoResolver: 'parties.editar', produce: ['party'], opciones: $opciones))
        ->toThrow(InvalidArgumentException::class, 'declara juntas sus opciones');

    app()->instance(RegistroProcesosIa::class, tap(new RegistroProcesosIa)->registrar($definicion));
    app(IaConfiguracionService::class)->sincronizar();
    $proceso = IaProceso::query()->where('codigo', 'opciones_ia')->firstOrFail();
    $modelo = sugModelo();
    $configuracion = app(IaConfiguracionService::class);
    $configuracion->asignarModelos($proceso->id, $modelo->id, null, $proceso->refresh()->version);
    $configuracion->autorizar($proceso->id, '10.00', $proceso->refresh()->version);
    $proceso->refresh()->update(['esta_activo' => true]);
    sugFalso()->responderValida(sugSalida());

    app(IaService::class)->sugerir('opciones_ia', sugEmpresa());

    expect(json_decode(sugFalso()->pedidos[0]->contexto, true)['opciones'])->toBe(['partidas' => [['codigo' => '01.02', 'nombre' => 'Cableado']]]);
});

it('un campo enviado tiene que ser una columna del registro, nunca una relación ni un accessor', function () {
    $definicion = new DefinicionProcesoIa('relacion_ia', 'Relación', 'Prueba.', 'Comercial', ['party'], [], ['party' => ['contactos']], [
        'dato' => ['type' => 'string'],
    ], permisoResolver: 'parties.editar', produce: ['party']);

    expect(fn () => app(RegistroProcesosIa::class)->revisar($definicion))->toThrow(InvalidArgumentException::class, 'no es una columna del registro');
});

it('el pedido va en la cola de la IA, que no lo vuelve a entregar mientras sigue corriendo', function () {
    config(['nucleo.ia.cola' => 'ia']);
    Queue::fake();
    sugEncendido(sugModelo());

    sugPedir(sugEmpresa());

    Queue::assertPushedOn('ia', SugerirConIa::class, fn (SugerirConIa $job): bool => $job->connection === 'ia');
    expect((new SugerirConIa('ejemplo_ia', 'party', 1, null, 1))->timeout)->toBeLessThan(config('queue.connections.ia.retry_after'))
        ->and(config('queue.connections.ia.retry_after'))->toBeGreaterThan(config('queue.connections.database.retry_after'));
});
