<?php

declare(strict_types=1);

use App\Modules\Crm\Cuentas\Models\Cuenta;
use App\Modules\Crm\Oportunidades\Enums\GanadorConocido;
use App\Modules\Crm\Oportunidades\Enums\LugarFaja;
use App\Modules\Crm\Oportunidades\Enums\ModoCajasFaja;
use App\Modules\Crm\Oportunidades\Estados\DefinicionOportunidad;
use App\Modules\Crm\Oportunidades\Models\Oportunidad;
use App\Modules\Crm\Oportunidades\OportunidadesServiceProvider;
use App\Modules\Crm\Oportunidades\Services\ContratosDeOportunidad;
use App\Modules\Crm\Oportunidades\Services\FilaFaja;
use App\Modules\Crm\Oportunidades\Services\LimiteCajasFaja;
use App\Modules\Crm\Oportunidades\Services\OportunidadesService;
use App\Modules\Nucleo\database\seeders\CatalogosSeeder;
use App\Modules\Nucleo\database\seeders\EstadosSeeder;
use App\Modules\Nucleo\database\seeders\ParametrosSeeder;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Enums\OrigenTipoCambio;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Models\Estado;
use App\Modules\Nucleo\Models\Moneda;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\TipoMotivo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\TipoCambioService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Modules\Crm\Oportunidades\Fixtures\OportunidadesDePrueba;

/*
 * Las consultas de la faja de vidrio (plan 2.21b, paso 2; D8 y D9): la tabla de cada lugar con búsqueda, orden y
 * páginas; las columnas de las salas; los totales de viñetas y terminales; y el límite de cajas. Empresas inventadas.
 */

beforeEach(function () {
    $this->seed([ParametrosSeeder::class, CatalogosSeeder::class, EstadosSeeder::class, PermisosSeeder::class]);
    $this->travelTo('2026-11-10 10:00');
    $this->cuenta = Cuenta::factory()->create();
    $this->gerencia = User::factory()->create()->givePermissionTo(['crm.oportunidades.editar', DefinicionOportunidad::CLAVE.'.perder']);
    $this->faja = app(OportunidadesService::class);
});

function fjMoneda(string $codigo): int
{
    return Moneda::query()->where('codigo', $codigo)->value('id') ?? Moneda::factory()->create(['codigo' => $codigo])->id;
}

/** Pone una oportunidad en un estado sin pasar por las transiciones: aquí solo importa lo que se lee. */
function fjEnEstado(Oportunidad $oportunidad, string $codigo, ?string $desde = null, ?string $cierreReal = null): Oportunidad
{
    DB::table('oportunidades')->where('id', $oportunidad->id)->update([
        'estado_id' => Estado::query()->where('documento_type', 'oportunidad')->where('codigo', $codigo)->value('id'),
        'en_etapa_desde' => $desde ?? '2026-11-10',
        'fecha_cierre_real' => $cierreReal,
    ]);

    return $oportunidad->refresh();
}

/** @param  array<string, mixed>  $datos */
function fjCrear(int $cuentaId, array $datos = []): Oportunidad
{
    return OportunidadesDePrueba::crear($cuentaId, $datos);
}

/**
 * @param  list<FilaFaja>  $filas
 * @return list<string>
 */
function fjNombres(array $filas): array
{
    return array_map(fn (FilaFaja $f): string => $f->nombre, $filas);
}

// ——— El límite de cajas (D8) ———

test('el límite decide el modo, cuántas cajas se dibujan y cuántas van a la pila', function (int $cantidad, ModoCajasFaja $modo, int $dibujadas, int $enPila) {
    $cajas = LimiteCajasFaja::para($cantidad);

    expect($cajas->modo)->toBe($modo)
        ->and($cajas->dibujadas)->toBe($dibujadas)
        ->and($cajas->enPila)->toBe($enPila);
})->with([
    'ninguna' => [0, ModoCajasFaja::Completas, 0, 0],
    'una' => [1, ModoCajasFaja::Completas, 1, 0],
    'seis' => [6, ModoCajasFaja::Completas, 6, 0],
    'siete' => [7, ModoCajasFaja::Codigo, 7, 0],
    'doce' => [12, ModoCajasFaja::Codigo, 12, 0],
    'trece' => [13, ModoCajasFaja::CodigoConPila, 11, 2],
    'cuarenta' => [40, ModoCajasFaja::CodigoConPila, 11, 29],
]);

test('los umbrales viven en un solo lugar y una cantidad negativa no se acepta', function () {
    expect([LimiteCajasFaja::HASTA_COMPLETAS, LimiteCajasFaja::HASTA_CODIGO, LimiteCajasFaja::DIBUJADAS_CON_PILA])->toBe([6, 12, 11])
        ->and(fn () => LimiteCajasFaja::para(-1))->toThrow(InvalidArgumentException::class)
        ->and(LugarFaja::Calificada->ordenDeLasCajas())->toBe(['monto', 'desc'])
        ->and(LugarFaja::Ganadas->ordenDeLasCajas())->toBe(['cierre', 'desc']);
});

// ——— La tabla de una zona (D9) ———

test('una zona trae solo sus abiertas, con cuenta, ejecutivo, días, atascada y sin próxima acción', function () {
    $ejecutivo = User::factory()->create(['name' => 'Rosa Quispe Inventada']);
    $vieja = fjEnEstado(fjCrear($this->cuenta->id, ['nombre' => 'Cámaras del colegio', 'ejecutivo_user_id' => $ejecutivo->id]), 'calificada', '2026-10-20');
    fjEnEstado(fjCrear($this->cuenta->id, ['nombre' => 'Redes del municipio']), 'calificada', '2026-11-05');
    fjCrear($this->cuenta->id, ['nombre' => 'En prospecto']);
    fjEnEstado(fjCrear($this->cuenta->id, ['nombre' => 'Anulada en calificada']), 'anulada');

    $pagina = $this->faja->tablaDeLaFaja(LugarFaja::Calificada, orden: 'dias', direccion: 'desc');
    $fila = $pagina->filas[0];

    // Calificada tiene 14 días de máximo: 21 días la atascan, 5 no.
    expect($pagina->total)->toBe(2)
        ->and(fjNombres($pagina->filas))->toBe(['Cámaras del colegio', 'Redes del municipio'])
        ->and($fila->id)->toBe($vieja->id)
        ->and($fila->numero)->toBe($vieja->numero)
        ->and($fila->etapa)->toBe('calificada')
        ->and($fila->cuenta)->toContain($this->cuenta->refresh()->party()->value('razon_social'))
        ->and($fila->ejecutivo)->toBe('Rosa Quispe Inventada')
        ->and($fila->diasEnEtapa)->toBe(21)
        ->and($fila->atascada)->toBeTrue()
        ->and($pagina->filas[1]->atascada)->toBeFalse()
        ->and($fila->proximaAccion)->toBeNull()
        ->and($fila->cierreEstimado->toDateString())->toBe($vieja->fecha_cierre_estimada->toDateString())
        ->and($fila->version)->toBe($vieja->version)
        ->and($fila->contrato)->toBeNull()
        ->and($fila->motivo)->toBeNull()
        ->and($this->faja->tablaDeLaFaja(LugarFaja::Prospecto)->total)->toBe(1)
        ->and($this->faja->tablaDeLaFaja(LugarFaja::Negociacion)->filas)->toBe([]);
});

test('busca por número o nombre sin tildes ni mayúsculas', function () {
    $camara = fjCrear($this->cuenta->id, ['nombre' => 'Cámaras del colegio']);
    $redes = fjCrear($this->cuenta->id, ['nombre' => 'Redes del municipio']);

    expect(fjNombres($this->faja->tablaDeLaFaja(LugarFaja::Prospecto, 'CAMARAS')->filas))->toBe(['Cámaras del colegio'])
        ->and(fjNombres($this->faja->tablaDeLaFaja(LugarFaja::Prospecto, $redes->numero)->filas))->toBe(['Redes del municipio'])
        ->and($this->faja->tablaDeLaFaja(LugarFaja::Prospecto, 'colegio')->filas[0]->id)->toBe($camara->id)
        ->and($this->faja->tablaDeLaFaja(LugarFaja::Prospecto, 'nada parecido')->total)->toBe(0)
        ->and($this->faja->tablaDeLaFaja(LugarFaja::Prospecto, '  ')->total)->toBe(2);
});

test('ordena por código, nombre, monto en soles, días y cierre, y rechaza otro orden', function () {
    app(TipoCambioService::class)->registrar('USD', CarbonImmutable::parse('2026-11-10'), '3.7500', '3.7600', OrigenTipoCambio::Manual);
    $b = fjCrear($this->cuenta->id, ['nombre' => 'B soles', 'monto_estimado' => '5000.00', 'fecha_cierre_estimada' => '2026-12-20']);
    fjCrear($this->cuenta->id, ['nombre' => 'A dólares', 'monto_estimado' => '2000.00', 'moneda_id' => fjMoneda('USD'), 'fecha_cierre_estimada' => '2026-12-01']);
    fjCrear($this->cuenta->id, ['nombre' => 'C euros sin T.C.', 'monto_estimado' => '90000.00', 'moneda_id' => fjMoneda('EUR'), 'fecha_cierre_estimada' => '2027-01-15']);
    DB::table('oportunidades')->where('id', $b->id)->update(['en_etapa_desde' => '2026-11-01']);
    $orden = fn (string $o, string $d): array => fjNombres($this->faja->tablaDeLaFaja(LugarFaja::Prospecto, orden: $o, direccion: $d)->filas);

    // 2 000 USD × 3.75 = 7 500 soles, más que 5 000; los euros sin T.C. van al final en las dos direcciones.
    expect($orden('monto', 'desc'))->toBe(['A dólares', 'B soles', 'C euros sin T.C.'])
        ->and($orden('monto', 'asc'))->toBe(['B soles', 'A dólares', 'C euros sin T.C.'])
        ->and($orden('nombre', 'asc'))->toBe(['A dólares', 'B soles', 'C euros sin T.C.'])
        ->and($orden('codigo', 'desc'))->toBe(['C euros sin T.C.', 'A dólares', 'B soles'])
        ->and($orden('dias', 'desc')[0])->toBe('B soles')
        ->and($orden('cierre', 'asc'))->toBe(['A dólares', 'B soles', 'C euros sin T.C.'])
        ->and(fn () => $orden('monto_estimado; drop table oportunidades', 'asc'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $orden('monto', 'sideways'))->toThrow(InvalidArgumentException::class);

    $filas = $this->faja->tablaDeLaFaja(LugarFaja::Prospecto, orden: 'monto', direccion: 'desc')->filas;
    expect([$filas[0]->monto, $filas[0]->moneda, $filas[0]->montoEnSoles])->toBe(['2000.00', 'USD', '7500.00'])
        ->and($filas[1]->montoEnSoles)->toBe('5000.00')
        ->and($filas[2]->montoEnSoles)->toBeNull();
});

test('pagina con el total de la consulta y un tope de filas por página', function () {
    foreach (range(1, 5) as $i) {
        fjCrear($this->cuenta->id, ['nombre' => "Proyecto {$i}"]);
    }

    $segunda = $this->faja->tablaDeLaFaja(LugarFaja::Prospecto, orden: 'nombre', pagina: 2, porPagina: 2);

    expect($segunda->total)->toBe(5)
        ->and($segunda->ultimaPagina())->toBe(3)
        ->and(fjNombres($segunda->filas))->toBe(['Proyecto 3', 'Proyecto 4'])
        ->and(fjNombres($this->faja->tablaDeLaFaja(LugarFaja::Prospecto, orden: 'nombre', pagina: 3, porPagina: 2)->filas))->toBe(['Proyecto 5'])
        ->and($this->faja->tablaDeLaFaja(LugarFaja::Prospecto, porPagina: 5000)->porPagina)->toBe(OportunidadesService::MAXIMO_POR_PAGINA_FAJA);
});

// ——— Las salas ———

test('la sala de ganadas trae las del período, de la más reciente a la más antigua, con su contrato o «—»', function () {
    $octubre = fjEnEstado(fjCrear($this->cuenta->id, ['nombre' => 'Ganada en octubre']), 'ganada', '2026-10-15', '2026-10-15');
    $primera = fjEnEstado(fjCrear($this->cuenta->id, ['nombre' => 'Ganada el 3']), 'ganada', '2026-11-03', '2026-11-03');
    $reciente = fjEnEstado(fjCrear($this->cuenta->id, ['nombre' => 'Ganada el 8']), 'ganada', '2026-11-08', '2026-11-08');
    fjEnEstado(fjCrear($this->cuenta->id, ['nombre' => 'Anulada']), 'anulada', cierreReal: '2026-11-09');
    app()->instance(ContratosDeOportunidad::class, new class($reciente->id) implements ContratosDeOportunidad
    {
        public function __construct(private int $conContrato) {}

        public function anularBorrador(int $oportunidadId, string $motivo): void {}

        public function etiquetasDeContratos(array $oportunidadIds): array
        {
            return in_array($this->conContrato, $oportunidadIds, true) ? [$this->conContrato => 'CT-2026-0099'] : [];
        }

        public function tieneDatos(): bool
        {
            return true;
        }
    });

    [$orden, $direccion] = LugarFaja::Ganadas->ordenDeLasCajas();
    $sala = $this->faja->tablaDeLaFaja(LugarFaja::Ganadas, orden: $orden, direccion: $direccion);

    expect(fjNombres($sala->filas))->toBe(['Ganada el 8', 'Ganada el 3'])
        ->and($sala->filas[0]->contrato)->toBe('CT-2026-0099')
        ->and($sala->filas[0]->fechaCierre?->toDateString())->toBe('2026-11-08')
        ->and($sala->filas[0]->atascada)->toBeFalse()
        ->and($sala->filas[1]->id)->toBe($primera->id)
        ->and($sala->filas[1]->contrato)->toBeNull()
        ->and($sala->filas[1]->motivo)->toBeNull();

    // Con el período en «año», octubre también entra.
    $parametros = app(ParametrosService::class);
    $parametros->cambiar(OportunidadesServiceProvider::PARAMETRO_PERIODO_CIERRE, 'anio', $parametros->ficha(OportunidadesServiceProvider::PARAMETRO_PERIODO_CIERRE)['version']);
    expect($this->faja->tablaDeLaFaja(LugarFaja::Ganadas, orden: 'cierre', direccion: 'asc')->filas[0]->id)->toBe($octubre->id);
});

test('la sala de perdidas trae el motivo, si la ganó otra empresa, quién y a qué precio', function () {
    $familia = TipoMotivo::query()->where('codigo', DefinicionOportunidad::CLAVE.'.perder')->firstOrFail();
    $motivo = Motivo::query()->where('tipo_motivo_id', $familia->id)->where('es_otro', false)->firstOrFail();
    $rival = Party::factory()->juridica()->create(['razon_social' => 'Competencia Inventada S.A.C.']);
    $conPrecio = fjCrear($this->cuenta->id, ['nombre' => 'Perdida con precio']);
    $sinPrecio = fjCrear($this->cuenta->id, ['nombre' => 'Perdida sin precio']);
    $nadie = fjCrear($this->cuenta->id, ['nombre' => 'Perdida sin ganador']);
    $this->faja->perder($conPrecio->id, $conPrecio->version, new MotivoElegido($motivo->id), GanadorConocido::Si, $rival->id, '18500.00', fjMoneda('PEN'), false, null, $this->gerencia);
    $this->faja->perder($sinPrecio->id, $sinPrecio->version, new MotivoElegido($motivo->id), GanadorConocido::Si, $rival->id, null, null, true, null, $this->gerencia);
    $this->faja->perder($nadie->id, $nadie->version, new MotivoElegido($motivo->id), GanadorConocido::NoSeSabe, null, null, null, false, null, $this->gerencia);

    $filas = [];
    foreach ($this->faja->tablaDeLaFaja(LugarFaja::Perdidas, orden: 'nombre')->filas as $fila) {
        $filas[$fila->nombre] = $fila;
    }

    expect(array_keys($filas))->toBe(['Perdida con precio', 'Perdida sin ganador', 'Perdida sin precio'])
        ->and($filas['Perdida con precio']->motivo)->toBe($motivo->nombre)
        ->and($filas['Perdida con precio']->ganadorConocido)->toBe(GanadorConocido::Si)
        ->and($filas['Perdida con precio']->ganador)->toBe('Competencia Inventada S.A.C.')
        ->and([$filas['Perdida con precio']->precio, $filas['Perdida con precio']->precioSimbolo])->toBe(['18500.00', Moneda::query()->where('codigo', 'PEN')->value('simbolo')])
        ->and($filas['Perdida con precio']->fechaCierre?->toDateString())->toBe('2026-11-10')
        ->and($filas['Perdida sin precio']->precio)->toBeNull()
        ->and($filas['Perdida sin precio']->precioDesconocido)->toBeTrue()
        ->and($filas['Perdida sin ganador']->ganadorConocido)->toBe(GanadorConocido::NoSeSabe)
        ->and($filas['Perdida sin ganador']->ganador)->toBeNull()
        ->and($filas['Perdida sin ganador']->contrato)->toBeNull();
});

// ——— Totales de viñetas y terminales ———

test('los totales cuentan cada lugar y suman en soles, sin anuladas y con las cerradas del período', function () {
    app(TipoCambioService::class)->registrar('USD', CarbonImmutable::parse('2026-11-10'), '3.7500', '3.7600', OrigenTipoCambio::Manual);
    fjCrear($this->cuenta->id, ['monto_estimado' => '1000.00']);
    fjCrear($this->cuenta->id, ['monto_estimado' => '100.00', 'moneda_id' => fjMoneda('USD')]);
    fjCrear($this->cuenta->id, ['monto_estimado' => '700.00', 'moneda_id' => fjMoneda('EUR')]);
    fjEnEstado(fjCrear($this->cuenta->id, ['monto_estimado' => '2500.00']), 'negociacion');
    fjEnEstado(fjCrear($this->cuenta->id, ['monto_estimado' => '8000.00']), 'ganada', cierreReal: '2026-11-04');
    fjEnEstado(fjCrear($this->cuenta->id, ['monto_estimado' => '9999.00']), 'ganada', cierreReal: '2026-10-30');
    fjEnEstado(fjCrear($this->cuenta->id, ['monto_estimado' => '3000.00']), 'perdida', cierreReal: '2026-11-02');
    fjEnEstado(fjCrear($this->cuenta->id, ['monto_estimado' => '4444.00']), 'anulada', cierreReal: '2026-11-02');

    $totales = $this->faja->totalesDeLaFaja();
    $resumen = array_map(fn ($t): array => [$t->cantidad, $t->totalEnSoles, $t->sinTipoCambio], $totales);

    // Prospecto: 1 000 + 100 × 3.75 = 1 375; los euros cuentan pero no suman. La ganada de octubre queda fuera del mes.
    expect(array_keys($totales))->toBe(array_map(fn (LugarFaja $l): string => $l->value, LugarFaja::cases()))
        ->and($resumen)->toBe([
            'prospecto' => [3, '1375.00', 1],
            'calificada' => [0, '0.00', 0],
            'propuesta_enviada' => [0, '0.00', 0],
            'negociacion' => [1, '2500.00', 0],
            'ganadas' => [1, '8000.00', 0],
            'perdidas' => [1, '3000.00', 0],
        ])
        ->and($totales['ganadas']->cantidad)->toBe($this->faja->tablaDeLaFaja(LugarFaja::Ganadas)->total);
});
