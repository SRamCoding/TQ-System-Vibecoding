<?php

declare(strict_types=1);

use App\Modules\Nucleo\Acciones\ActualizarUbigeos;
use App\Modules\Nucleo\database\seeders\PermisosSeeder;
use App\Modules\Nucleo\Excepciones\AccionRechazada;
use App\Modules\Nucleo\Excepciones\PadronUbigeosNoDisponible;
use App\Modules\Nucleo\Models\Ubigeo;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\RegistroAcciones\EjecutorAcciones;
use App\Modules\Nucleo\RegistroAcciones\RegistroAcciones;
use Illuminate\Support\Facades\Http;
use Tests\Modules\Nucleo\Fixtures\XlsxDePrueba;

beforeEach(function () {
    config(['nucleo.ubigeos.url' => 'https://datos.prueba/ubigeos.xlsx', 'nucleo.ubigeos.minimo_distritos' => 1]);
    test()->seed(PermisosSeeder::class);
    Ubigeo::factory()->create(['codigo' => '140103', 'departamento' => 'LAMBAYEQUE', 'provincia' => 'CHICLAYO', 'distrito' => 'ETEN']);
});

function actualizarUbigeos(User $usuario): mixed
{
    return app(EjecutorAcciones::class)->ejecutar('nucleo.actualizar-ubigeos', [], $usuario);
}

test('el núcleo la registra y su permiso existe', function () {
    expect(app(RegistroAcciones::class)->todas())->toHaveKey('nucleo.actualizar-ubigeos', ActualizarUbigeos::class)
        ->and(app(ActualizarUbigeos::class)->permiso())->toBe('ubigeos.actualizar');
});

test('descarga la lista del INEI y la aplica a la base a nombre de la persona', function () {
    Http::fake(['datos.prueba/*' => Http::response(XlsxDePrueba::inei([
        ['140103', 'LAMBAYEQUE', 'CHICLAYO', 'CIUDAD ETEN'],
        ['140121', 'LAMBAYEQUE', 'CHICLAYO', 'NUEVO'],
    ]))]);
    $rosa = User::factory()->create()->givePermissionTo('ubigeos.actualizar');

    $diferencia = actualizarUbigeos($rosa);

    expect($diferencia->nuevos)->toHaveCount(1)
        ->and($diferencia->renombrados)->toHaveCount(1)
        ->and(Ubigeo::query()->where('codigo', '140103')->value('distrito'))->toBe('CIUDAD ETEN')
        ->and(Ubigeo::query()->where('codigo', '140121')->value('created_by'))->toBe($rosa->id)
        ->and(Ubigeo::query()->where('codigo', '140103')->value('updated_by'))->toBe($rosa->id);
});

test('si la descarga falla no cambia nada y el error se puede mostrar', function () {
    Http::fake(['datos.prueba/*' => Http::response('bloqueado', 418)]);

    expect(fn () => actualizarUbigeos(User::factory()->create()->givePermissionTo('ubigeos.actualizar')))
        ->toThrow(PadronUbigeosNoDisponible::class, 'HTTP 418');
    expect(Ubigeo::query()->where('codigo', '140103')->value('distrito'))->toBe('ETEN');
});

test('rechaza a quien no tiene permiso, sin descargar', function () {
    Http::fake();

    expect(fn () => actualizarUbigeos(User::factory()->create()))->toThrow(AccionRechazada::class);
    Http::assertNothingSent();
});
