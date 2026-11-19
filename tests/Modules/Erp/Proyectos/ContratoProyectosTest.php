<?php

declare(strict_types=1);

use App\Modules\Erp\Proyectos\Estados\DefinicionProyecto;
use App\Modules\Erp\Proyectos\Models\Proyecto;
use App\Modules\Erp\Proyectos\Services\DatosProyecto;
use App\Modules\Erp\Proyectos\Services\EnMemoria\ProyectosEnMemoria;
use App\Modules\Erp\Proyectos\Services\ProyectoRechazado;
use App\Modules\Erp\Proyectos\Services\ProyectosEnBase;
use App\Modules\Erp\Proyectos\Services\ProyectosService;
use App\Modules\Nucleo\database\seeders\NucleoSeeder;
use App\Modules\Nucleo\Estados\MotivoElegido;
use App\Modules\Nucleo\Models\Motivo;
use App\Modules\Nucleo\Models\Party;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Services\EmpresaService;
use App\Modules\Nucleo\Services\ParametrosService;
use App\Modules\Nucleo\Services\TransicionesService;
use Carbon\CarbonImmutable;

/*
 * Contrato de C2 (plan 2.1, D1 y D4): las mismas pruebas contra la versión real y la falsa, para que la falsa que usan
 * otros módulos no se aleje de la real. Lo que solo la real puede revisar (otras tablas, eventos, acciones) está en
 * ProyectosTest.php.
 */

beforeEach(function () {
    test()->seed(NucleoSeeder::class);
    $techquk = Party::factory()->juridica()->create(['razon_social' => 'Techquk S.A.C.']);
    app(ParametrosService::class)->cambiarDesdeTexto(EmpresaService::PARAMETRO, $techquk->numero_documento, 1);
    test()->techqukId = $techquk->id;
    test()->cliente = Party::factory()->juridica()->create(['razon_social' => 'Municipalidad Inventada']);
    test()->usuario = User::factory()->create();
    test()->actingAs(test()->usuario);
});

/**
 * Cada versión, con la forma de cerrar y anular que tiene: la real, por sus transiciones; la falsa, a mano.
 *
 * @return array{servicio: ProyectosService, cerrar: Closure(int): void, anular: Closure(int): void}
 */
function cpVersion(string $cual): array
{
    if ($cual === 'falsa') {
        $falsa = new ProyectosEnMemoria(test()->techqukId);

        return [
            'servicio' => $falsa,
            'cerrar' => fn (int $id) => $falsa->ponerEnEstado($id, DefinicionProyecto::CERRADO),
            'anular' => fn (int $id) => $falsa->ponerEnEstado($id, DefinicionProyecto::ANULADO),
        ];
    }

    $transicionar = function (int $id, string $codigo, ?MotivoElegido $motivo = null): void {
        $proyecto = Proyecto::query()->findOrFail($id);
        $usuario = User::factory()->create()->givePermissionTo("erp.proyectos.proyecto.{$codigo}");
        app(TransicionesService::class)->transicionar($proyecto, $codigo, $proyecto->version, $usuario, $motivo, $motivo === null ? null : 'Creado por error');
    };

    return [
        'servicio' => app(ProyectosEnBase::class),
        'cerrar' => fn (int $id) => $transicionar($id, 'cerrar'),
        'anular' => function (int $id) use ($transicionar): void {
            $otro = Motivo::query()->whereHas('tipoMotivo', fn ($query) => $query->where('codigo', 'erp.proyectos.proyecto.anular'))->where('codigo', Motivo::CODIGO_OTRO)->value('id');
            $transicionar($id, 'anular', new MotivoElegido((int) $otro, 'Se registró dos veces'));
        },
    ];
}

function cpDatos(string $nombre = 'Videovigilancia Plaza Inventada', ?int $cliente = null, string $moneda = 'PEN', string $inicio = '2026-11-10', string $fin = '2027-01-31'): DatosProyecto
{
    return new DatosProyecto($nombre, test()->usuario->id, CarbonImmutable::parse($inicio), CarbonImmutable::parse($fin), $cliente, $moneda);
}

dataset('versiones de C2', ['real', 'falsa']);

test('un proyecto interno nace en preparación, con Techquk como cliente y un código PRY', function (string $cual) {
    $proyecto = cpVersion($cual)['servicio']->crear(cpDatos(), test()->usuario);

    expect($proyecto->estado)->toBe(DefinicionProyecto::EN_PREPARACION)
        ->and($proyecto->esInterno)->toBeTrue()
        ->and($proyecto->contratoId)->toBeNull()
        ->and($proyecto->clientePartyId)->toBe(test()->techqukId)
        ->and($proyecto->aceptaCostos)->toBeTrue()
        ->and($proyecto->codigo)->toMatch('/^PRY-\d{4}-\d{4}$/')
        ->and($proyecto->nombre)->toBe('Videovigilancia Plaza Inventada');
})->with('versiones de C2');

test('el que nace de un contrato lleva el contrato y su cliente, y no es interno', function (string $cual) {
    $proyecto = cpVersion($cual)['servicio']->crearDesdeContrato(18, cpDatos(cliente: test()->cliente->id));

    expect($proyecto->contratoId)->toBe(18)
        ->and($proyecto->esInterno)->toBeFalse()
        ->and($proyecto->clientePartyId)->toBe(test()->cliente->id)
        ->and($proyecto->estado)->toBe(DefinicionProyecto::EN_PREPARACION);
})->with('versiones de C2');

test('desde un contrato, sin cliente o sin contrato, se rechaza', function (string $cual) {
    $servicio = cpVersion($cual)['servicio'];

    expect(fn () => $servicio->crearDesdeContrato(18, cpDatos()))->toThrow(ProyectoRechazado::class, 'Falta el cliente')
        ->and(fn () => $servicio->crearDesdeContrato(0, cpDatos(cliente: test()->cliente->id)))->toThrow(ProyectoRechazado::class, 'Falta el contrato');
})->with('versiones de C2');

test('sin nombre, con el fin antes del inicio o en otra moneda que soles, se rechaza', function (string $cual) {
    $servicio = cpVersion($cual)['servicio'];

    expect(fn () => $servicio->crear(cpDatos(nombre: '   '), test()->usuario))->toThrow(ProyectoRechazado::class, 'nombre')
        ->and(fn () => $servicio->crear(cpDatos(inicio: '2026-12-01', fin: '2026-11-30'), test()->usuario))->toThrow(ProyectoRechazado::class, 'anterior a la de inicio')
        ->and(fn () => $servicio->crear(cpDatos(moneda: 'USD'), test()->usuario))->toThrow(ProyectoRechazado::class, 'etapa 3');
})->with('versiones de C2');

test('el resumen de un proyecto que no existe se rechaza', function (string $cual) {
    expect(fn () => cpVersion($cual)['servicio']->resumen(999999))->toThrow(ProyectoRechazado::class, 'no existe');
})->with('versiones de C2');

test('iniciar la ejecución pasa de preparación a ejecución, y repetirla no hace nada', function (string $cual) {
    $servicio = cpVersion($cual)['servicio'];
    $proyecto = $servicio->crear(cpDatos(), test()->usuario);

    $servicio->iniciarEjecucion($proyecto->id);
    $servicio->iniciarEjecucion($proyecto->id);

    expect($servicio->resumen($proyecto->id)->estado)->toBe(DefinicionProyecto::EN_EJECUCION)
        ->and($servicio->resumen($proyecto->id)->aceptaCostos)->toBeTrue();
})->with('versiones de C2');

test('iniciar la ejecución de un proyecto cerrado o anulado falla con un mensaje claro', function (string $cual) {
    $version = cpVersion($cual);
    $servicio = $version['servicio'];
    $cerrado = $servicio->crear(cpDatos(), test()->usuario);
    $servicio->iniciarEjecucion($cerrado->id);
    $version['cerrar']($cerrado->id);
    $anulado = $servicio->crear(cpDatos(), test()->usuario);
    $version['anular']($anulado->id);

    expect(fn () => $servicio->iniciarEjecucion($cerrado->id))->toThrow(ProyectoRechazado::class, 'está cerrado: no puede pasar a ejecución')
        ->and(fn () => $servicio->iniciarEjecucion($anulado->id))->toThrow(ProyectoRechazado::class, 'está anulado: no puede pasar a ejecución')
        ->and($servicio->resumen($cerrado->id)->aceptaCostos)->toBeFalse()
        ->and($servicio->resumen($anulado->id)->aceptaCostos)->toBeFalse();
})->with('versiones de C2');

test('activos busca por código o nombre, sin acentos, y deja fuera los cerrados y anulados', function (string $cual) {
    $version = cpVersion($cual);
    $servicio = $version['servicio'];
    $camaras = $servicio->crear(cpDatos(nombre: 'Cámaras del Mercado Inventado'), test()->usuario);
    $servicio->crear(cpDatos(nombre: 'Red de fibra'), test()->usuario);
    $cerrado = $servicio->crear(cpDatos(nombre: 'Cámaras antiguas'), test()->usuario);
    $servicio->iniciarEjecucion($cerrado->id);
    $version['cerrar']($cerrado->id);

    expect(array_map(fn ($p) => $p->id, $servicio->activos('camaras')))->toBe([$camaras->id])
        ->and(array_map(fn ($p) => $p->id, $servicio->activos(strtolower($camaras->codigo))))->toBe([$camaras->id])
        ->and($servicio->activos())->toHaveCount(2);
})->with('versiones de C2');
