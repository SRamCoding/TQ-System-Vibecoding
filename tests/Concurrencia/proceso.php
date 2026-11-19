<?php

declare(strict_types=1);

/*
 * Un proceso de la prueba de concurrencia (plan 1.6). Lo lanza `ConcurrenciaTest` varias veces a la vez, cada uno con
 * su propia conexión a `techquk_concurrencia`: así compiten de verdad por las mismas filas, sin la transacción de
 * RefreshDatabase que los aislaría. Espera hasta el instante de partida que le dan, hace una sola cosa e imprime el
 * resultado en una línea JSON.
 *
 * Uso: php tests/Concurrencia/proceso.php <partida en microsegundos> numero|party|transicion|firmar|ficha|aprobar-arbol|fijar-monto|
 *      aprobar-orden|crear-cuenta|crear-oportunidad|desactivar-cuenta|ganar|aceptar-cotizacion|cerrar-oportunidad [argumentos]
 */

use App\Modules\Crm\Cotizaciones\Enums\TipoRespaldo;
use App\Modules\Crm\Cotizaciones\Services\CotizacionesService;
use App\Modules\Crm\Cuentas\Services\CuentasService;
use App\Modules\Crm\Cuentas\Services\DatosCuenta;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\Services\DatosOportunidad;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Erp\Presupuestos\Models\ArbolPartidas;
use App\Modules\Erp\Presupuestos\Models\PartidaControl;
use App\Modules\Erp\Presupuestos\Services\ArbolPartidasService;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Estados\RegistroDocumentos;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Numeracion\FormatoNumeracion;
use App\Modules\Nucleo\Parties\DatosParty;
use App\Modules\Nucleo\Services\AprobacionesService;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\NumeracionService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\PartiesService;
use App\Modules\Nucleo\Services\TransicionesService;
use App\Modules\Nucleo\Tiempo\Calendario;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Modules\Erp\Presupuestos\Fixtures\ComprometerLineasDePrueba;
use Tests\Modules\Erp\Presupuestos\Fixtures\DefinicionOrdenDePrueba;
use Tests\Modules\Erp\Presupuestos\Fixtures\OrdenDePrueba;
use Tests\Modules\Nucleo\Fixtures\DefinicionPrueba;
use Tests\Modules\Nucleo\Fixtures\DocumentoPrueba;

require __DIR__.'/../../vendor/autoload.php';

// Nunca contra otra base: la prueba la reconstruye desde cero. En una copia de trabajo, la suya (tests/bootstrap.php).
$baseConcurrencia = getenv('TECHQUK_BASE_CONCURRENCIA') ?: 'techquk_concurrencia';
if (getenv('DB_DATABASE') !== $baseConcurrencia) {
    fwrite(STDERR, "Solo corre contra {$baseConcurrencia}.\n");
    exit(2);
}

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Y de nuevo con la conexión real: una configuración en caché o un DB_URL podrían apuntar a otra.
if (DB::selectOne('SELECT current_database() AS base')->base !== $baseConcurrencia) {
    fwrite(STDERR, "La conexión no es {$baseConcurrencia}.\n");
    exit(2);
}

// Como en las demás pruebas, nada sale a internet.
Http::preventStrayRequests();

[, $partida, $modo] = $argv;
$argumentos = array_slice($argv, 3);

DocumentoPrueba::crearMorphMap();
app(RegistroDocumentos::class)->registrar(new DefinicionPrueba);
// Plan 2.2: la orden de prueba con líneas de costo.
OrdenDePrueba::crearMorphMap();
app(RegistroDocumentos::class)->efecto('erp.presupuestos.prueba.comprometer', ComprometerLineasDePrueba::class);
app(RegistroDocumentos::class)->registrar(new DefinicionOrdenDePrueba);

// Ya conectado (por la guarda): todos parten a la vez.
usleep(max(0, (int) $partida - (int) (microtime(true) * 1_000_000)));

try {
    $resultado = match ($modo) {
        // Retiene el contador un momento antes de confirmar: los demás tienen que esperar su turno.
        'numero' => DB::transaction(function (): array {
            $numero = app(NumeracionService::class)->siguiente('documento_prueba', new FormatoNumeracion('REQ'), CarbonImmutable::now());
            usleep(30_000);

            return ['numero' => $numero];
        }),
        'party' => (function (): array {
            $party = app(PartiesService::class)->obtenerOCrear(new DatosParty('6', '20123456786', razonSocial: 'Proveedor Inventado S.A.C.'));

            return ['id' => $party->id, 'creada' => $party->creada];
        })(),
        'transicion' => (function (array $argumentos): array {
            [$documentoId, $codigo, $correo] = $argumentos;
            $documento = DocumentoPrueba::query()->findOrFail((int) $documentoId);
            $historial = app(TransicionesService::class)->transicionar($documento, $codigo, $documento->version, User::query()->where('email', $correo)->firstOrFail());

            return ['historial' => $historial->id];
        })($argumentos),
        'firmar' => (function (array $argumentos): array {
            [$documentoId, $correo] = $argumentos;
            $firma = app(AprobacionesService::class)->firmar('documento_prueba', (int) $documentoId, 'aprobar', User::query()->where('email', $correo)->firstOrFail());

            return ['firma' => $firma->id];
        })($argumentos),
        // Plan 1.15, D3: elegir la ficha de la empresa o desactivarla. Retiene su transacción un momento antes de confirmar,
        // para que el otro tenga que esperar o ver lo que hizo.
        'ficha' => DB::transaction(function () use ($argumentos): array {
            [$accion, $partyId] = $argumentos;
            $party = Party::query()->findOrFail((int) $partyId);
            $parametros = app(ParametrosService::class);

            $accion === 'elegir'
                ? $parametros->cambiarDesdeTexto(EmpresaService::PARAMETRO, $party->numero_documento, $parametros->ficha(EmpresaService::PARAMETRO)['version'])
                : app(PartiesService::class)->desactivar($party->id, $party->version);
            usleep(200_000);

            return ['accion' => $accion];
        }),
        // Plan 2.1, D5: aprobar el árbol mientras otro cambia un monto. Cada uno retiene su transacción un momento antes de
        // confirmar: el otro espera la fila del árbol y ve lo que hizo el primero.
        'aprobar-arbol' => DB::transaction(function () use ($argumentos): array {
            [$arbolId, $correo] = $argumentos;
            $arbol = ArbolPartidas::query()->findOrFail((int) $arbolId);
            app(TransicionesService::class)->transicionar($arbol, 'aprobar', $arbol->version, User::query()->where('email', $correo)->firstOrFail());
            usleep(200_000);

            return ['accion' => 'aprobar'];
        }),
        'fijar-monto' => DB::transaction(function () use ($argumentos): array {
            [$partidaId, $monto] = $argumentos;
            app(ArbolPartidasService::class)->fijarPresupuestado((int) $partidaId, $monto, PartidaControl::query()->findOrFail((int) $partidaId)->version);
            usleep(200_000);

            return ['accion' => 'fijar'];
        }),
        // Plan 2.20, D2: dos personas crean a la vez la cuenta del mismo RUC (con la ficha ya registrada o sin ella).
        // Retiene su transacción un momento antes de confirmar: la otra tiene que esperar y ver lo que hizo.
        'crear-cuenta' => DB::transaction(function () use ($argumentos): array {
            [$ruc, $correo, $origenId] = $argumentos;
            $creada = app(CuentasService::class)->crear(new DatosCuenta(
                $ruc,
                User::query()->where('email', $correo)->firstOrFail()->id,
                (int) $origenId,
                razonSocial: 'Distribuidora Inventada S.A.C.',
            ));
            usleep(200_000);

            return ['cuenta' => $creada->cuenta->id];
        }),
        // Plan 2.2, D3 y D4: la última firma de una orden con líneas de costo, que revisa el exceso con las partidas
        // bloqueadas y sube el comprometido. Retiene su transacción para que el otro espere.
        'aprobar-orden' => DB::transaction(function () use ($argumentos): array {
            [$ordenId, $correo] = $argumentos;
            app(AprobacionesService::class)->firmar('orden_prueba', (int) $ordenId, 'aprobar', User::query()->where('email', $correo)->firstOrFail());
            usleep(200_000);

            return ['accion' => 'aprobar-orden'];
        }),
        // Plan 2.21, D6: crear una oportunidad mientras otro desactiva su cuenta. Bloqueo compartido de la cuenta; retiene
        // su transacción para que el otro tenga que esperar.
        'crear-oportunidad' => DB::transaction(function () use ($argumentos): array {
            [$cuentaId, $correo, $monedaId, $lineaId] = $argumentos;
            $creada = app(OportunidadesService::class)->crear((int) $cuentaId, new DatosOportunidad(
                'Cableado de prueba', null, '1000.00', (int) $monedaId, CarbonImmutable::parse('2026-12-31'), (int) $lineaId,
            ), [], User::query()->where('email', $correo)->firstOrFail());
            usleep(200_000);

            return ['oportunidad' => $creada->id];
        }),
        'desactivar-cuenta' => DB::transaction(function () use ($argumentos): array {
            [$cuentaId, $correo, $motivoId] = $argumentos;
            $version = (int) DB::table('cuentas')->where('id', (int) $cuentaId)->value('version');
            app(CuentasService::class)->desactivar((int) $cuentaId, $version, (int) $motivoId, null, false, User::query()->where('email', $correo)->firstOrFail());
            usleep(200_000);

            return ['accion' => 'desactivar'];
        }),
        // Plan 2.21, D6: dos ganadas a la vez de la misma cuenta (ítems de una misma licitación). Bloqueo exclusivo de la
        // cuenta desde el inicio: la segunda espera a la primera, sin trabarse.
        'ganar' => DB::transaction(function () use ($argumentos): array {
            [$oportunidadId, $correo] = $argumentos;
            $version = (int) DB::table('oportunidades')->where('id', (int) $oportunidadId)->value('version');
            app(OportunidadesService::class)->ganar((int) $oportunidadId, $version, CarbonImmutable::parse(Calendario::hoy()), '1000.00', null, User::query()->where('email', $correo)->firstOrFail());
            usleep(200_000);

            return ['accion' => 'ganar'];
        }),
        // Plan 2.22b: aceptar una cotización mientras otro pierde o anula su oportunidad. Aceptar bloquea antes la
        // oportunidad (C13); los dos retienen su transacción para que el otro espere.
        'aceptar-cotizacion' => DB::transaction(function () use ($argumentos): array {
            [$cotizacionId, $correo] = $argumentos;
            $version = (int) DB::table('cotizaciones')->where('id', (int) $cotizacionId)->value('version');
            app(CotizacionesService::class)->aceptar((int) $cotizacionId, $version, CarbonImmutable::parse(Calendario::hoy()), TipoRespaldo::OrdenCompra, 'OC-1', null, null, User::query()->where('email', $correo)->firstOrFail());
            usleep(200_000);

            return ['accion' => 'aceptar'];
        }),
        'cerrar-oportunidad' => DB::transaction(function () use ($argumentos): array {
            [$oportunidadId, $codigo, $correo, $motivoId] = $argumentos;
            $oportunidad = Oportunidad::query()->findOrFail((int) $oportunidadId);
            app(TransicionesService::class)->transicionar($oportunidad, $codigo, $oportunidad->version, User::query()->where('email', $correo)->firstOrFail(), new MotivoElegido((int) $motivoId));
            usleep(200_000);

            return ['accion' => $codigo];
        }),
        default => throw new InvalidArgumentException("Modo desconocido: {$modo}"),
    };
    echo json_encode(['ok' => true, ...$resultado]), "\n";
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e::class, 'mensaje' => $e->getMessage()]), "\n";
}
