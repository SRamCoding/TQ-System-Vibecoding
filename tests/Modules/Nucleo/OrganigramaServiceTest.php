<?php

declare(strict_types=1);

use App\Modules\Nucleo\Enums\TipoUnidadOrganizativa;
use App\Modules\Nucleo\Excepciones\OrganigramaRechazado;
use App\Modules\Nucleo\Excepciones\VersionDesactualizada;
use App\Modules\Nucleo\Models\AuditoriaCambio;
use App\Modules\Nucleo\Models\Puesto;
use App\Modules\Nucleo\Models\UnidadOrganizativa;
use App\Modules\Nucleo\Models\User;
use App\Modules\Nucleo\Organigrama\DatosUnidad;
use App\Modules\Nucleo\Services\OrganigramaService;

/*
 * Reglas del organigrama (plan 1.4, D5): sin ciclos, el jefe es un puesto de la misma unidad con un solo ocupante,
 * y lo activo no cuelga de lo desactivado. Organigrama inventado.
 */

function organigrama(): OrganigramaService
{
    return app(OrganigramaService::class);
}

function nuevaUnidad(string $codigo, ?int $superiorId = null, TipoUnidadOrganizativa $tipo = TipoUnidadOrganizativa::Area): UnidadOrganizativa
{
    return UnidadOrganizativa::query()->findOrFail(organigrama()->crearUnidad(new DatosUnidad($codigo, "Unidad {$codigo}", $tipo, $superiorId)));
}

function moverUnidad(UnidadOrganizativa $unidad, ?int $superiorId): void
{
    $unidad->refresh();
    organigrama()->editarUnidad($unidad->id, new DatosUnidad($unidad->codigo, $unidad->nombre, $unidad->tipo, $superiorId), $unidad->version);
}

test('crea unidades en árbol, con el código en mayúsculas y su bitácora', function () {
    $gerencia = nuevaUnidad('gg', tipo: TipoUnidadOrganizativa::Gerencia);
    $logistica = nuevaUnidad('LOG', $gerencia->id);

    expect($gerencia->codigo)->toBe('GG')
        ->and($logistica->padre?->id)->toBe($gerencia->id)
        ->and(AuditoriaCambio::query()->where('auditable_type', 'unidad_organizativa')->count())->toBe(2);
});

test('una unidad no se puede mover dentro de sí misma ni de un descendiente suyo', function () {
    $a = nuevaUnidad('A');
    $b = nuevaUnidad('B', $a->id);
    $c = nuevaUnidad('C', $b->id);

    expect(fn () => moverUnidad($a, $c->id))->toThrow(OrganigramaRechazado::class, '«Unidad A» no puede depender de «Unidad C»')
        ->and(fn () => moverUnidad($a, $a->id))->toThrow(OrganigramaRechazado::class)
        ->and($a->refresh()->parent_id)->toBeNull();

    moverUnidad($c, $a->id);

    expect($c->refresh()->parent_id)->toBe($a->id);
});

test('no se crea ni se mueve una unidad bajo una desactivada', function () {
    $a = nuevaUnidad('A');
    $b = nuevaUnidad('B');
    organigrama()->desactivarUnidad($a->id, 1);

    expect(fn () => nuevaUnidad('C', $a->id))->toThrow(OrganigramaRechazado::class, 'está desactivada')
        ->and(fn () => moverUnidad($b, $a->id))->toThrow(OrganigramaRechazado::class, 'está desactivada');
});

test('código y nombre repetidos dan un mensaje claro', function () {
    nuevaUnidad('A');

    expect(fn () => nuevaUnidad('a'))->toThrow(OrganigramaRechazado::class, 'Ya hay una unidad con el código A.')
        ->and(fn () => organigrama()->crearUnidad(new DatosUnidad('Z', 'unidad a', TipoUnidadOrganizativa::Area)))
        ->toThrow(OrganigramaRechazado::class, 'Ya hay una unidad llamada «unidad a» en el mismo nivel');
});

test('editar con una versión vieja se rechaza', function () {
    $a = nuevaUnidad('A');
    moverUnidad($a, null);
    organigrama()->editarUnidad($a->id, new DatosUnidad('A', 'Otro nombre', TipoUnidadOrganizativa::Area), 1);

    expect(fn () => organigrama()->editarUnidad($a->id, new DatosUnidad('A', 'Tercero', TipoUnidadOrganizativa::Area), 1))
        ->toThrow(VersionDesactualizada::class);
});

test('no se desactiva una unidad con unidades o puestos activos; vacía, sí, y se vuelve a activar', function () {
    $a = nuevaUnidad('A');
    $b = nuevaUnidad('B', $a->id);
    $puesto = organigrama()->crearPuesto($b->id, 'Asistente');

    expect(fn () => organigrama()->desactivarUnidad($a->id, 1))->toThrow(OrganigramaRechazado::class, 'tiene unidades o puestos activos')
        ->and(fn () => organigrama()->desactivarUnidad($b->id, 1))->toThrow(OrganigramaRechazado::class, 'tiene unidades o puestos activos');

    organigrama()->desactivarPuesto($puesto, 1);
    organigrama()->desactivarUnidad($b->id, 1);
    organigrama()->desactivarUnidad($a->id, 1);

    expect(fn () => organigrama()->activarUnidad($b->id, 2))->toThrow(OrganigramaRechazado::class, 'está desactivada');

    organigrama()->activarUnidad($a->id, 2);
    organigrama()->activarUnidad($b->id, 2);

    expect($b->refresh()->es_activa)->toBeTrue()
        ->and(fn () => organigrama()->activarUnidad($b->id, 3))->toThrow(OrganigramaRechazado::class, 'ya está activa');
});

test('el jefe es el ocupante activo del puesto de jefatura; vacante, nadie', function () {
    $a = nuevaUnidad('A');
    $puesto = organigrama()->crearPuesto($a->id, 'Jefe de logística');
    organigrama()->designarJefatura($a->id, $puesto, 1);

    expect(organigrama()->jefeDe($a->id))->toBeNull();

    $ana = User::factory()->create(['puesto_id' => $puesto]);
    User::factory()->inactivo()->create(['puesto_id' => $puesto]);

    expect(organigrama()->jefeDe($a->refresh())?->id)->toBe($ana->id);
});

test('la jefatura es un puesto activo de la misma unidad y de una sola persona', function () {
    $a = nuevaUnidad('A');
    $b = nuevaUnidad('B');
    $ajeno = organigrama()->crearPuesto($b->id, 'Jefe B');
    $compartido = organigrama()->crearPuesto($a->id, 'Asistente');
    User::factory()->count(2)->create(['puesto_id' => $compartido]);
    $inactivo = organigrama()->crearPuesto($a->id, 'Antiguo');
    organigrama()->desactivarPuesto($inactivo, 1);

    expect(fn () => organigrama()->designarJefatura($a->id, $ajeno, 1))->toThrow(OrganigramaRechazado::class, 'de la misma unidad')
        ->and(fn () => organigrama()->designarJefatura($a->id, $compartido, 1))->toThrow(OrganigramaRechazado::class, 'más de un ocupante')
        ->and(fn () => organigrama()->designarJefatura($a->id, $inactivo, 1))->toThrow(OrganigramaRechazado::class, 'está desactivado');
});

test('un puesto de jefatura ocupado no admite a otra persona; uno común, sí', function () {
    $a = nuevaUnidad('A');
    $jefatura = organigrama()->crearPuesto($a->id, 'Jefe');
    $comun = organigrama()->crearPuesto($a->id, 'Asistente');
    organigrama()->designarJefatura($a->id, $jefatura, 1);
    $ana = User::factory()->create(['puesto_id' => $jefatura]);
    $luis = User::factory()->create(['puesto_id' => $comun]);

    expect(fn () => organigrama()->bloquearParaOcupar($jefatura, $luis->id))->toThrow(OrganigramaRechazado::class, 'ya tiene ocupante')
        ->and(organigrama()->bloquearParaOcupar($jefatura, $ana->id)->id)->toBe($jefatura)
        ->and(organigrama()->bloquearParaOcupar($comun, $ana->id)->id)->toBe($comun);
});

test('un puesto con ocupantes activos o que es jefatura no se desactiva', function () {
    $a = nuevaUnidad('A');
    $ocupado = organigrama()->crearPuesto($a->id, 'Asistente');
    User::factory()->create(['puesto_id' => $ocupado]);
    $jefatura = organigrama()->crearPuesto($a->id, 'Jefe');
    organigrama()->designarJefatura($a->id, $jefatura, 1);

    expect(fn () => organigrama()->desactivarPuesto($ocupado, 1))->toThrow(OrganigramaRechazado::class, 'reasígnalos primero')
        ->and(fn () => organigrama()->desactivarPuesto($jefatura, 1))->toThrow(OrganigramaRechazado::class, 'reasígnalos primero');
});

test('el puesto se renombra pero no cambia de unidad; no se crea en una unidad desactivada', function () {
    $a = nuevaUnidad('A');
    $puesto = organigrama()->crearPuesto($a->id, 'Asistente');
    organigrama()->editarPuesto($puesto, 'Asistente de compras', 1);
    organigrama()->crearPuesto($a->id, 'Practicante');

    expect(Puesto::query()->findOrFail($puesto)->nombre)->toBe('Asistente de compras')
        ->and(fn () => organigrama()->crearPuesto($a->id, 'asistente de COMPRAS'))->toThrow(OrganigramaRechazado::class, 'ya tiene un puesto llamado')
        ->and(fn () => organigrama()->crearPuesto($a->id, '   '))->toThrow(OrganigramaRechazado::class, 'lleva código y nombre');

    $b = nuevaUnidad('B');
    organigrama()->desactivarUnidad($b->id, 1);

    expect(fn () => organigrama()->crearPuesto($b->id, 'Asistente'))->toThrow(OrganigramaRechazado::class, 'está desactivada');
});
